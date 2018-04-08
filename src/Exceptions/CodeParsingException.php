<?php

namespace Codeshift\Exceptions;

use \PhpParser\Error;


/**
 * Exception class thrown when a source file could not be parsed.
 * Alternative name for exception \PhpParser\Error.
 */
class CodeParsingException extends \PhpParser\Error {
}

