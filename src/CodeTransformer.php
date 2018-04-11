<?php

namespace Codeshift;

use \Codeshift\Exceptions\{FileNotFoundException, CorruptCodemodException, CodeParsingException};
use \PhpParser\{Lexer, NodeDumper, NodeTraverser, NodeVisitor, ParserFactory, PrettyPrinter};
use \PhpParser\Error as PhpParserError;


/**
 * Wrapper for executing code transformations defined by a single codemod
 * on a source string, file or directory.
 * Also provides convenient methods for dumping the AST of a source string or file.
 */
class CodeTransformer {
    private $oLexer;
    private $oParser;
    private $oCloneTraverser;
    private $oPrinter;
    private $oCodemod;
    private $oTracer;

    /**
     * Constructor.
     * Optionally takes a custom tracer to be used for protocolling
     * and an initialized codemod to be used for code transformations.
     *
     * @param AbstractTracer|null $oTracer Optional tracer to use
     * @param AbstractCodemod|null $oCodemod Optional codemod to use
     */
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

    /**
     * Sets the given tracer to be used for protocolling.
     *
     * @param AbstractTracer $oTracer The tracer object
     * @return void
     */
    public function setTracer(AbstractTracer $oTracer) {
        $this->oTracer = $oTracer;
    }

    /**
     * Sets the given codemod to be used for code transformations.
     *
     * @param AbstractCodemod $oCodemod The initialized codemod
     * @return void
     */
    public function setCodemod(AbstractCodemod $oCodemod) {
        $this->oCodemod = $oCodemod;
    }

    /**
     * Uses the current set codemod to transform the given code string
     * and returns the resulting code string.
     *
     * @param string $codeString The input code to transform
     * @param array|null $codeInfoMap
     *  An optional list of named attributes providing information about the code to transform.
     *  The information is passed to the codemod to be returned by {@see AbstractCodemod::getCodeInformation()}.
     * @throws CodeParsingException If the input code cannot be parsed
     * @return string The resulting code after transformation
     */
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

    /**
     * Uses the current set codemod to transform the given file and returns the resulting file.
     *
     * @uses CodeTransformer::runOnCode()
     * 
     * @param string $filePath Path of the input file to transform
     * @param string|null $outputPath
     *  Optional path of the file to write the result to. If not given, the original file is changed.
     * @throws FileNotFoundException If the input file cannot be found
     * @throws CodeParsingException If the input file cannot be parsed
     * @return string The absolute path of the result file
     */
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
            $this->oTracer->traceFileTransformation($inputFilePath, $outputFilePath, ($inputCode != $outputCode));
        }

        return $outputFilePath;
    }

    /**
     * Uses the current set codemod to transform the given directory and returns the resulting directory.
     * The directory is traversed recursively.
     * 
     * Only files having any of the following extensions are considered to be transformed:
     * 'php', 'php4', 'php5', 'phtml'
     *
     * @uses CodeTransformer::runOnFile()
     * 
     * @param string $dirPath Path of the input directory to transform recursively
     * @param string|null $outputDirPath
     *  Optional path of the root directory to write the results to. If not given, the original files are changed.
     * @param array|null $ignorePaths
     *  Optional list of absolute paths of files or directories to ignore for transformation.
     *  If a directory is ignored, all of its files and its sub-directories are ignored.
     *  None of the ignored files or directories will be copied to the output directory.
     * @throws FileNotFoundException If the input directory cannot be found
     * @throws CodeParsingException If the a file of the input directory cannot be parsed
     * @return string The absolute path of the root directory of the results
     */
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

    /**
     * Uses the current set codemod to transform the file or directory of the given path 
     * and returns the path of result file or directory.
     *
     * @uses CodeTransformer::runOnFile()
     * @uses CodeTransformer::runOnDirectory()
     *
     * @param string $path Path of the input file or directory to transform
     * @param string|null $outputPath
     *  Optional path of the file or root directory to write the results to. If not given, the original file(s) are changed.
     * @param array|null $ignorePaths
     *  Optional list of absolute paths of files or directories to ignore for directory transformation.
     *  {@see CodeTransformer::runOnDirectory()}
     * @throws FileNotFoundException If the input file or directory cannot be found
     * @throws CodeParsingException If the input file or a file of the input directory cannot be parsed
     * @return string The absolute path of the result file / root directory of the results
     */
    public function runOnPath($path, $outputPath=null, $ignorePaths=[]) {
        if (is_dir($path)) {
            return $this->runOnDirectory($path, $outputPath, $ignorePaths);
        } else {
            return $this->runOnFile($path, $outputPath);
        }
    }


    /**
     * Parses and builds the AST (Abstract Syntax Tree) of the given code string
     * and returns it in textual human-readable form.
     *
     * @param string $codeString The input code to parse
     * @param array|null $codeInfoMap
     *  An optional list of named attributes providing information about the code to parse.
     * @throws CodeParsingException If the input code cannot be parsed
     * @return string The textual human-readable form of the AST
     */
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

    /**
     * Parses and builds the AST (Abstract Syntax Tree) of the given source file
     * and returns it in textual human-readable form.
     *
     * @param string $filePath Path of the input file to transform
     * @param string $outputPath Optional path of the file to write the result dump to.
     * @throws FileNotFoundException If the input file cannot be found
     * @throws CodeParsingException If the input file cannot be parsed
     * @return string The textual human-readable form of the AST
     */
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


    /**
     * Creates all directories required to match the given path.
     *
     * @param string $dirPath Path of a directory to create
     * @param AbstractTracer $oTracer Optional tracer to use for logging warnings
     * @return string
     *  The result path of the directory. It differs from the input, if there was a file with same path.
     */
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

    /**
     * Ensures existence of all directories required to match the given path and returns a file path from it.
     * If the input path is a directory, the filename is taken from `$referenceFilePath`.
     *
     * @param string $outputPath Path of a file or directory to check
     * @param string $referenceFilePath File name or path to use in case of a directory
     * @param AbstractTracer $oTracer Optional tracer to use for logging warnings
     * @return string The result path for a file.
     */
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

