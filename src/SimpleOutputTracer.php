<?php

namespace Codeshift;


/**
 * Simple implementation of the tracing interface that outputs any text to STDOUT/STDERR.
 * Falls back to `echo`, if no STDOUT/STDERR streams do not exist.
 */
class SimpleOutputTracer extends AbstractTracer {

    /**
     * Indention to prefix each output line with.
     */
    const INDENT = '  ';

    /**
     * Control chars to postfix each line with.
     */
    const EOL = PHP_EOL;

    /**
     * Returns the given text as line to be printed.
     * 
     * @uses static::INDENT to indent the text.
     * @uses static::EOL to end the line.
     *
     * @param string $text Input text
     * @return string Output text
     */
    public function getLine($text) {
        return static::INDENT.$text.static::EOL;
    }

    /**
     * Prints the given text to STDOUT.
     * 
     * @uses SimpleOutputTracer::getLine() to format the text as output line.
     *
     * @param string $text The text to print. Optional
     * @return void
     */
    public function writeLine($text='') {
        if (is_resource(STDOUT)) {
            fwrite(STDOUT, $this->getLine($text));
        } else {
            echo $this->getLine($text);
        }
    }

    /**
     * Prints the given text to STDERR.
     *
     * @param string $text The text to write. Optional
     * @return void
     */
    public function writeErrLine($text='') {
        if (is_resource(STDERR)) {
            fwrite(STDERR, $this->getLine($text));
        } else {
            echo $this->getLine($text);
        }
    }

}


