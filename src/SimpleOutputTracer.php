<?php

namespace Codeshift;


/**
 * Simple implementation of the tracing interface that outputs any text to STDOUT/STDERR.
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
     * @param string $text The text to print
     * @return void
     */
    public function writeLine($text='') {
        fwrite(STDOUT, $this->getLine($text));
    }

    /**
     * Prints the given text to STDERR.
     *
     * @param string $text The text to write
     * @return void
     */
    public function writeErrLine($text='') {
        fwrite(STDERR, $this->getLine($text));
    }

}


