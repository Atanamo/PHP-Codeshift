
PHP-Codeshift
=============

PHP-Codeshift* is a lightweight wrapper for the excellent library [PHP-Parser](https://github.com/nikic/PHP-Parser).\
It mainly provides an easy-to-use API for running own codemod definition files on multiple PHP source files.

\* Yes, the name is totally stolen from project "[jscodeshift](https://github.com/facebook/jscodeshift)" ;-)


Requirements
------------

Due to dependencies to [PHP-Parser](https://github.com/nikic/PHP-Parser), following PHP versions are required:

* Running codeshift: PHP 7.0 or higher
* Code to transform: PHP 5.2 or higher


Features
--------

* Simple CLI for:
    * Dumping the AST of a file to stout or an output file
    * Transforming a single source file using a codemod
    * Transforming sources in a directory tree using a codemod
* Clear API for writing a codemod

\
\
More to come...
