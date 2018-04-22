<?php

namespace Codeshift;


/**
 * Abstraction for logging information while runtime, having a semantic interface.
 */
abstract class AbstractTracer {

    /**
     * Most inner routine to be used to output/record/write any kind of text.
     *
     * @param string $text The text to write. Optional
     * @return void
     */
    abstract public function writeLine($text='');

    /**
     * Sub routine to be used to output/record/write any kind of error text.
     *
     * @param string $text The text to write. Optional
     * @return void
     */
    public function writeErrLine($text='') {
        $this->writeLine($text);
    }

    /**
     * Logs an information message.
     *
     * @param string $message The information message
     * @return void
     */
    public function inform($message) {
        $this->writeLine('Info: '.$message);
    }

    /**
     * Logs a warning that occurred.
     *
     * @param string $message The warning message
     * @return void
     */
    public function warn($message) {
        $this->writeLine('Warning: '.$message);
    }

    /**
     * Logs an error or exception that occurred.
     *
     * @param string $message The error message
     * @return void
     */
    public function error($message) {
        $this->writeErrLine('Error: '.$message);
    }

    /**
     * Logs the successful transformation of the given file.
     *
     * @param string $inputFilePath Path of the file
     * @param string $outputFilePath Path of the result file
     * @param bool $fileChanged Determines whether or not the file content has changed
     * @return void
     */
    public function traceFileTransformation($inputFilePath, $outputFilePath, $fileChanged) {
        $touched = ($fileChanged ? '' : ' (No changes)');

        if ($inputFilePath != $outputFilePath) {
            $this->writeLine("Transformed \"{$inputFilePath}\" --> \"{$outputFilePath}\"{$touched}");
        } else {
            $this->writeLine("Transformed \"{$inputFilePath}\"{$touched}");
        }
    }

    /**
     * Logs the successful loading of a the given codemod before its execution.
     *
     * @param AbstractCodemod $oCodemod Instance of codemod class
     * @param string $codemodPath Path of the codemod file
     * @param bool $fromCache Determines whether or not the codemod was already loaded
     * @return void
     */
    public function traceCodemodLoaded(AbstractCodemod $oCodemod, $codemodPath, $fromCache=false) {
        $codemodClassName = get_class($oCodemod);

        if ($fromCache) {
            $this->writeLine("Reloaded codemod \"{$codemodClassName}\" from cache\"");
        } else {
            $this->writeLine("Loaded codemod \"{$codemodClassName}\" from file \"{$codemodPath}\"");
        }
    }

    /**
     * Logs the given exception.
     *
     * @param \Exception $oException The exception that occurred
     * @param bool $withStackTrace Set true to log the stack trace (Defaults to false)
     * @return void
     */
    public function traceException(\Exception $oException, $withStackTrace=false) {
        $this->error($oException->getMessage());

        if ($withStackTrace) {
            $this->writeErrLine('Stack:');

            $traceString = $oException->getTraceAsString();
            $traceLines = explode("\n", $traceString);

            foreach($traceLines as $line) {
                $this->writeErrLine($line);
            }
        }

        if ($p = $oException->getPrevious()) {
            $this->writeErrLine();
            $this->traceException($p, $withStackTrace);
        }
    }

}

