<?php

namespace Codeshift;

use \Codeshift\CodeTransformer;
use \Codeshift\Exceptions\{FileNotFoundException, CorruptCodemodException};


/**
 * Main class for loading and executing an arbitrary number of codemods
 * on a source file or directory.
 */
class CodemodRunner {
    private $oTransformer;
    private $codemodPaths = [];

    /**
     * Constructor.
     * Optionally takes an initial codemod to be added to execution schedule.
     *
     * @param string $codemodFilePath Optional path of initial codemod file
     */
    public function __construct($codemodFilePath=null) {
        $this->oTransformer = new CodeTransformer();

        if ($codemodFilePath) {
            $this->addCodemod($codemodFilePath);
        }
    }

    /**
     * Adds a codemod defintion file to be executed.
     *
     * @param string $filePath Path of codemod file
     * @return void
     */
    public function addCodemod($filePath) {
        $this->codemodPaths[] = $filePath;
    }

    /**
     * Adds multiple codemod defintion files to be executed.
     *
     * @param array $filePaths List of paths of codemod files
     * @return void
     */
    public function addCodemods(array $filePaths) {
        foreach ($filePaths as $filePath) {
            $this->addCodemod($filePath);
        }
    }

    /**
     * Removes all added codemod definition files (from execution schedule).
     *
     * @return void
     */
    public function clearCodemods() {
        $this->codemodPaths = [];
    }

    /**
     * Loads the given codemod and prepares the code transformation routines from it.
     *
     * @param string $codemodFilePath Path of codemod file
     * @throws FileNotFoundException If the codemod is not found
     * @throws CorruptCodemodException If the codemod cannot be used for the preparation
     * @return CodeTransformer
     */
    public function loadCodemodToTransformer($codemodFilePath) {
        $codemodClass = null;

        // Load codemod class
        if (file_exists($codemodFilePath)) {
            $codemodClass = require $codemodFilePath;

            if (!class_exists($codemodClass)) {
                throw new CorruptCodemodException("Missing exported class of codemod: \"{$codemodFilePath}\"");
            }
        }
        else {
            throw new FileNotFoundException("Could not find codemod \"{$codemodFilePath}\"");
        }

        // Init the codemod
        try {
            $oCodemod = new $codemodClass();
        }
        catch (Exception $ex) {
            throw new CorruptCodemodException("Failed to init codemod: {$ex->getMessage()}", null, $ex);
        }

        // Prepare the transformer
        if (is_subclass_of($oCodemod, '\Codeshift\AbstractCodemod')) {
            $this->oTransformer->setCodemod($oCodemod);
        }
        else {
            throw new CorruptCodemodException("Invalid class type of codemod: \"{$codemodFilePath}\"");
        }

        return $this->oTransformer;
    }

    /**
     * Transforms the source code of the given target path
     * by executing all scheduled codemods (in same order they were added).
     * 
     * If no output path is given, the input source is updated directly by the result source.
     * Else, if an output path is given, the input source remains untouched.
     * All codemods following the first one are executed on the output path.
     * 
     * Files matching the ignore paths are not written to output path.
     *
     * @param string $targetPath Path of the input source file or directory
     * @param string $outputPath Path of the output file or directory
     * @param array $ignorePaths List of file or directory paths to exclude from transformation
     * @throws FileNotFoundException If a codemod is not found
     * @throws CorruptCodemodException If a codemod cannot be interpreted
     * @return void
     */
    public function execute($targetPath, $outputPath=null, array $ignorePaths=[]) {
        $currTargetPath = $targetPath;
        $currOutputPath = $outputPath;

        foreach ($this->codemodPaths as $codemodFilePath) {
            $this->oTransformer = $this->loadCodemodToTransformer($codemodFilePath);

            $resultOutputPath = $this->oTransformer->runOnPath($currTargetPath, $currOutputPath, $ignorePaths);

            // Execute further codemods on output path
            if($outputPath != null) {
                $currTargetPath = $resultOutputPath;
                $currOutputPath = null;
            }
        }
    }

}

