<?php

namespace Codeshift;

use \PhpParser\{Lexer, NodeDumper, NodeTraverser, NodeVisitor, Parser, ParserFactory, PrettyPrinter};
use \PhpParser\Error as PhpParserError;


class CodeTransformer {
    private $oLexer;
    private $oParser;
    private $oCloneTraverser;
    private $oPrinter;
    private $oCodemod;

    public function __construct() {
        $this->oLexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
            ],
        ]);

        $oFactory = new ParserFactory();
        $this->oParser = $oFactory->create(ParserFactory::PREFER_PHP7, $this->oLexer);

        $this->oCloneTraverser = new NodeTraverser();
        $this->oCloneTraverser->addVisitor(new NodeVisitor\CloningVisitor());

        $this->oPrinter = new PrettyPrinter\Standard();
    }

    public function setCodemod(AbstractCodemod $oCodemod) {
        $this->oCodemod = $oCodemod;
    }

    public function runOnCode($codeString, $codeInfoMap=null) {
        try {
            $oldStmts = $this->oParser->parse($codeString);
            $oldTokens = $this->oLexer->getTokens();

            // Set references to old statements
            $newStmts = $this->oCloneTraverser->traverse($oldStmts);

            // Set additional info to codemod
            $this->oCodemod->setCodeInformation($codeInfoMap);

            // Transform using the codemod
            if ($this->oCodemod != null) {
                try {
                    $newStmts = $this->oCodemod->transformStatements($newStmts);
                }
                catch (Exception $e) {
                    echo 'Failed to call transform function: ', $e->getMessage();  // TODO: Exception
                }
            }

            $newCode = $this->oPrinter->printFormatPreserving($newStmts, $oldStmts, $oldTokens);

            return $newCode;
        }
        catch (PhpParserError $e) {
            echo 'Parse error: ', $e->getMessage();
        }

        return $codeString;
    }

    public function runOnFile($filePath, $outputPath=null) {
        $filePath = realpath($filePath);
        $outputPath = $outputPath ?: $filePath;
        $outputFilePath = self::getSolidOutputPathForFile($outputPath, $filePath);

        $inputCode = file_get_contents($filePath);

        $outputCode = $this->runOnCode($inputCode, [
            'inputFile' => $filePath,
            'outputFile' => $outputFilePath,
        ]);

        file_put_contents($outputFilePath, $outputCode);

        // Print info
        $outputFilePath = realpath($outputFilePath);

        if ($filePath != $outputFilePath) {
            echo "  $filePath --> $outputFilePath\n";
        } else {
            echo "  Modified $filePath\n";
        }

        return $outputFilePath;
    }

    public function runOnDirectory($dirPath, $outputDirPath=null, $ignorePaths=[]) {
        $outputDirPath = $outputDirPath ?: $dirPath;
        $outputDirPath = self::createMissingDirectories($outputDirPath);

        $dirPath = realpath($dirPath);
        $outputDirPath = realpath($outputDirPath);

        // Exclude output dir from scanning (Avoid endless recursion for output targets within $dirPath)
        $ignorePaths[] = $outputDirPath;

        // Recursively traverse directory
        $entriesListdata = scandir($dirPath);

        foreach ($entriesListdata as $entry) {
            if ($entry != '.' AND $entry != '..') {
                $currPath = $dirPath.DIRECTORY_SEPARATOR.$entry;
                $currOutputPath = $outputDirPath.DIRECTORY_SEPARATOR.$entry;

                if (!in_array(realpath($currPath), $ignorePaths)) {
                    if (is_dir($currPath)) {
                        $this->runOnDirectory($currPath, $currOutputPath, $ignorePaths);
                    }
                    else {
                        $fileExtension = pathinfo($currPath, PATHINFO_EXTENSION);
                        $fileExtension = strtolower($fileExtension);

                        if (in_array($fileExtension, ['php', 'php4', 'php5', 'phtml'])) {
                            $this->runOnFile($currPath, $currOutputPath);
                        }
                    }
                }
            }
        }

        return $outputDirPath;
    }

    public function runOnPath($path, $outputPath=null, $ignorePaths=[]) {
        $path = realpath($path);

        if (is_dir($path)) {
            return $this->runOnDirectory($path, $outputPath, $ignorePaths);
        } else {
            return $this->runOnFile($path, $outputPath);
        }
    }


    public function dumpCodeAST($codeString) {
        try {
            $ast = $this->oParser->parse($codeString);
            $dumper = new NodeDumper();

            return $dumper->dump($ast);
        }
        catch (PhpParserError $e) {
            echo 'Parse error: ', $e->getMessage();
        }

        return '';
    }

    public function dumpFileAST($filePath, $outputPath=null) {
        $codeString = file_get_contents($filePath);
        $astPrint = $this->dumpCodeAST($codeString);

        if ($outputPath) {
            $outputFilePath = self::getSolidOutputPathForFile($outputPath, $filePath);
            file_put_contents($outputFilePath, $astPrint);
        }

        return $astPrint;
    }


    private static function createMissingDirectories($dirPath) {
        if (!is_dir($dirPath) AND file_exists($dirPath)) {
            $dirPath = $dirPath.'_';
            echo "Warning: Path of output directory points to existing file! Changing directory to: $dirPath\n";
        }

        if (!is_dir($dirPath)) {
            mkdir($dirPath, '0777', true);
        }

        return $dirPath;
    }

    private static function getSolidOutputPathForFile($outputPath, $referenceFilePath) {
        if (is_dir($outputPath) OR (!is_file($outputPath) AND !pathinfo($outputPath, PATHINFO_EXTENSION))) {
            // Output path is directory, ensure directories and generate output file name
            $dirPath = self::createMissingDirectories($outputPath);
            $outputPath = $dirPath.DIRECTORY_SEPARATOR.basename($referenceFilePath);
        }
        else {
            // Output path is file, just ensure directories
            $dirPath = self::createMissingDirectories(dirname($outputPath));
            $outputPath = $dirPath.DIRECTORY_SEPARATOR.basename($outputPath);
        }

        return preg_replace('/([\\/])+/', DIRECTORY_SEPARATOR, $outputPath);
    }

}

