<?php

namespace Codeshift\Exceptions;

/**
 * Exception class thrown when a codemod cannot be loaded due to e.g. 
 * insufficient API compliance or an inner runtime exception.
 */
class CorruptCodemodException extends \RuntimeException {
}

