<?php

namespace Codeshift;

use \PhpParser\{Lexer, NodeDumper, NodeFinder, NodeTraverser, NodeVisitor, Parser, ParserFactory, PrettyPrinter};
use \PhpParser\Error as PhpParserError;


class CodeTransformer {
    private $oLexer;
    private $oParser;
    private $oCloneTraverser;
    private $oCustomTraverser;
    private $oNodeFinder;
    private $oPrinter;
    private $manualTransformFunc = null;
    private $hasCustomVisitors = false;

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

        $this->oCustomTraverser = new NodeTraverser();
        $this->oNodeFinder = new NodeFinder();

        $this->oPrinter = new PrettyPrinter\Standard();
    }

    public function addVisitor($oVisitor) {
        $this->oCustomTraverser->addVisitor($oVisitor);
        $this->hasCustomVisitors = true;
    }

    public function addVisitors(array $visitors) {
        foreach ($visitors as $oVisitor) {
            $this->oCustomTraverser->addVisitor($oVisitor);
        }
        $this->hasCustomVisitors = true;
    }

    public function clearVisitors() {
        $this->oCustomTraverser = new NodeTraverser();
        $this->hasCustomVisitors = false;
    }

    public function setManualTransformFunction($func) {
        $this->manualTransformFunc = $func;
    }


    public function runOnCode($codeString) {
        try {
            $oldStmts = $this->oParser->parse($codeString);
            $oldTokens = $this->oLexer->getTokens();

            // Set references to old statements
            $newStmts = $this->oCloneTraverser->traverse($oldStmts);

            // May transform by custom traversing
            if ($this->hasCustomVisitors) {
                $newStmts = $this->oCustomTraverser->traverse($newStmts);
            }

            // May transform manually
            if (is_callable($this->manualTransformFunc)) {
                try {
                    $func = $this->manualTransformFunc;
                    $newStmts = $func($newStmts, $this->oNodeFinder);
                }
                catch (Exception $e) {
                    echo 'Failed to call manual transform function: ', $e->getMessage();
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

        $outputCode = $this->runOnCode($inputCode);

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

