<?php

namespace Codeshift;

use \Codeshift\Exceptions\{FileNotFoundException, CorruptCodemodException, CodeParsingException};
use \PhpParser\{Lexer, NodeDumper, NodeTraverser, NodeVisitor, Parser, ParserFactory, PrettyPrinter};
use \PhpParser\Error as PhpParserError;


class CodeTransformer {
    private $oLexer;
    private $oParser;
    private $oCloneTraverser;
    private $oPrinter;
    private $oCodemod;
    private $oTracer;

    public function __construct(AbstractTracer $oTracer=null, AbstractCodemod $oCodemod=null) {
        $this->oTracer = $oTracer;
        $this->oCodemod = $oCodemod;

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

    public function setTracer(AbstractTracer $oTracer) {
        $this->oTracer = $oTracer;
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

            // Process the codemod
            if ($this->oCodemod != null) {
                // Set additional info to codemod
                $this->oCodemod->setCodeInformation($codeInfoMap);

                // Transform using the codemod
                $newStmts = $this->oCodemod->transformStatements($newStmts);
            }

            // Write back code changes
            $newCode = $this->oPrinter->printFormatPreserving($newStmts, $oldStmts, $oldTokens);

            return $newCode;
        }
        catch (PhpParserError $ex) {
            if (isset($codeInfoMap['inputFile'])) {
                throw new CodeParsingException("Failed to parse file \"{$codeInfoMap['inputFile']}\" :: {$ex->getMessage()}", null, $ex);
            } else {
                throw new CodeParsingException("Parse error :: {$ex->getMessage()}", null, $ex);
            }
        }

        return $codeString;
    }

    public function runOnFile($filePath, $outputPath=null) {
        if (!file_exists($filePath) OR !is_file($filePath)) {
            throw new FileNotFoundException("Could not find file \"{$filePath}\"");
        }

        $inputFilePath = realpath($filePath);
        $outputFilePath = $outputPath ?: $inputFilePath;
        $outputFilePath = self::getSolidOutputPathForFile($outputFilePath, $inputFilePath, $this->oTracer);

        $inputCode = file_get_contents($inputFilePath);

        $outputCode = $this->runOnCode($inputCode, [
            'inputFile' => $inputFilePath,
            'outputFile' => $outputFilePath,
        ]);

        file_put_contents($outputFilePath, $outputCode);

        // Print info
        $outputFilePath = realpath($outputFilePath);

        if ($this->oTracer != null) {
            $this->oTracer->traceFileTransformation($inputFilePath, $outputFilePath);
        }

        return $outputFilePath;
    }

    public function runOnDirectory($dirPath, $outputDirPath=null, $ignorePaths=[]) {
        if (!file_exists($dirPath) OR !is_dir($dirPath)) {
            throw new FileNotFoundException("Could not find directory \"{$dirPath}\"");
        }

        $outputDirPath = $outputDirPath ?: $dirPath;
        $outputDirPath = self::createMissingDirectories($outputDirPath, $this->oTracer);

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
        if (is_dir($path)) {
            return $this->runOnDirectory($path, $outputPath, $ignorePaths);
        } else {
            return $this->runOnFile($path, $outputPath);
        }
    }


    public function dumpCodeAST($codeString, $codeInfoMap=null) {
        try {
            $ast = $this->oParser->parse($codeString);
            $dumper = new NodeDumper();

            return $dumper->dump($ast);
        }
        catch (PhpParserError $ex) {
            if (isset($codeInfoMap['inputFile'])) {
                throw new CodeParsingException("Failed to parse file \"{$codeInfoMap['inputFile']}\" :: {$ex->getMessage()}", null, $ex);
            } else {
                throw new CodeParsingException("Parse error :: {$ex->getMessage()}", null, $ex);
            }
        }

        return '';
    }

    public function dumpFileAST($filePath, $outputPath=null) {
        // Check input file
        if (!file_exists($filePath) OR !is_file($filePath)) {
            throw new FileNotFoundException("Could not find file \"{$filePath}\"");
        }

        // Handle arguments
        $inputFilePath = realpath($filePath);
        $outputFilePath = null;

        if ($outputPath) {
            $outputFilePath = self::getSolidOutputPathForFile($outputPath, $inputFilePath, $this->oTracer);
        }

        // Dump the file AST
        $codeString = file_get_contents($inputFilePath);
        $astPrint = $this->dumpCodeAST($codeString, [
            'inputFile' => $inputFilePath,
            'outputFile' => $outputFilePath,
        ]);

        if ($outputPath) {
            file_put_contents($outputFilePath, $astPrint);
        }

        return $astPrint;
    }


    private static function createMissingDirectories($dirPath, $oTracer=null) {
        if (!is_dir($dirPath) AND file_exists($dirPath)) {
            $dirPath = $dirPath.'_';

            if ($oTracer != null) {
                $oTracer->warn("Path of output directory points to existing file! Changing directory to: \"{$dirPath}\"");
            }
        }

        if (!is_dir($dirPath)) {
            mkdir($dirPath, '0777', true);
        }

        return $dirPath;
    }

    private static function getSolidOutputPathForFile($outputPath, $referenceFilePath, $oTracer=null) {
        if (is_dir($outputPath) OR (!is_file($outputPath) AND !pathinfo($outputPath, PATHINFO_EXTENSION))) {
            // Output path is directory, ensure directories and generate output file name
            $dirPath = self::createMissingDirectories($outputPath, $oTracer);
            $outputPath = $dirPath.DIRECTORY_SEPARATOR.basename($referenceFilePath);
        }
        else {
            // Output path is file, just ensure directories
            $dirPath = self::createMissingDirectories(dirname($outputPath), $oTracer);
            $outputPath = $dirPath.DIRECTORY_SEPARATOR.basename($outputPath);
        }

        return preg_replace('/([\\/])+/', DIRECTORY_SEPARATOR, $outputPath);
    }

}

