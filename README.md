
PHP-Codeshift
=============

PHP-Codeshift* is a lightweight wrapper for the excellent library [PHP-Parser](https://github.com/nikic/PHP-Parser).  
It mainly provides an easy-to-use API for running own codemod definition files on multiple PHP source files.

\* Yes, the name is totally stolen from project "[jscodeshift](https://github.com/facebook/jscodeshift)" ;-)


Requirements
------------

Due to dependencies to [PHP-Parser](https://github.com/nikic/PHP-Parser) 4.x, following PHP versions are required:

* Running codeshift: PHP 7.0 or higher
* Target code to be transformed: PHP 5.2 or higher


Features
--------

* Simple CLI for:
    * Dumping the AST of a file to stout or an output file
    * Transforming a single source file using a codemod
    * Transforming sources in a directory tree using a codemod
* Clear API for writing a codemod
* Clear API for executing codemods programmatically


Install & Run
-------------

The easiest way to install Codeshift is to add it to your project using [Composer](https://getcomposer.org).

1. Require the library as a dependency using Composer: 

    ```text
    php composer.phar require atanamo/php-codeshift
    ```

2. Install the library:

    ```text
    php composer.phar install
    ```

3. Execute the Codeshift CLI (Print help):

    ```text
    vendor/bin/codeshift --help
    ```



CLI usage
=========

To execute a codemod file on a source directory:
```text
vendor/bin/codeshift --mod=/my/codemod.php --src=/my/project/src
```

Use `--out` to not change original source:
```text
vendor/bin/codeshift --mod=/my/codemod.php --src=/my/project/src --out=/transformed/src
```

Dump AST to file:
```text
vendor/bin/codeshift --ast=/my/script.php --out=/my/script_ast.txt
```

Note:
If you are on windows, you may need to use `call` to access the binary:
```text
call vendor/bin/codeshift ...
```



Writing a codemod
=================

Codemod file
------------

A codemod is a PHP file that defines the transformations to do on your PHP source code / source file.

The only thing needed in the file is to export a class derived from `Codeshift\AbstractCodemod`.
This class can define/override one or more of the provided hook methods:

```php
<?php

use Codeshift\AbstractCodemod;

class YourAwesomeCodemod extends AbstractCodemod {

    public function init() {
        // Do some own initializations and environment setup here
    }

    public function beforeTraversalTransform(array $statements): array {
        // Do some manual transformations here,
        // they are applied before starting the main code traversal

        return $statements;
    }

    public function afterTraversalTransform(array $statements): array {
        // Do some manual transformations here,
        // they are applied after the main code traversal was done

        return $statements;
    }
}

return YourAwesomeCodemod;  // Export of the codemod class
```

Please see source file [`<repo>/src/AbstractCodemod.php`](https://github.com/Atanamo/PHP-Codeshift/blob/master/src/AbstractCodemod.php) for detail documentaion of the `AbstractCodemod` class.

Also see [`<repo>/samples/foobar_codemod.php`](https://github.com/Atanamo/PHP-Codeshift/blob/master/samples/foobar_codemod.php) for an example codemod.


Ways to transform
-----------------

Basically, you can differ two ways of how to transform your code:

1. Traversal transformation using so-called "Node visitors"
2. Manual transformation using hard-core API

For **traversal transformation** you almost always only need the `init` hook of a codemod.
The `before` and `after` hooks are intended for the more specific tasks of a **manual transformation**.
But of course you can mix them as you need.

For the transformations themselves you will have to rely heavily on the API of [PHP-Parser](https://github.com/nikic/PHP-Parser).


Traversal transformation
------------------------

Traversal transformation means to transform the AST while traversing it.
The transformation itself is defined by one or more "node visitors".
The visitors are applied to each node of the AST.

Please see the excellent documentation of PHP-Parser for this:  
[PHP-Parser/doc/component/Walking_the_AST](https://github.com/nikic/PHP-Parser/blob/master/doc/component/Walking_the_AST.markdown)

While PHP-Parser refers to the class `PhpParser\NodeTraverser` for applying a visitor,
PHP-Codeshift provides some convenient mechanism for this.

Normally you only have to add your visitor in the `init` hook of your codemod:

```php
    public function init() {
        // Init the your visitor
        $visitor = new YourAwesomeVisitor();

        // Schedule a traversal run on the code that uses the visitor
        $this->addTraversalTransform($visitor);
    }
```

Each call to `addTraversalTransform()` will create one `PhpParser\NodeTraverser` internally, which is then used to do one traversal run on the AST.

If you want to use multiple visitors on one single run, just pass them all:

```php
$this->addTraversalTransform($visitor1, $visitor2, $visitor3);
```

You may define the visitor classes in the codemod file or require them from other files.

Note that each of your visitors must implement the interface `PhpParser\NodeVisitor`. Therefor it's recommended to simply extend the abstract class `PhpParser\NodeVisitorAbstract`.
Do not forget to use the correct namespace.


Manual transformation
---------------------

Manual transformation includes everything that differs from traversal transformation.

You can use the hook methods `beforeTraversalTransform` and/or `afterTraversalTransform` to manipulate the passed statement nodes as you like.

In that case, the AST as a whole is represented by the statement nodes.
Therefor, to simplify finding specific nodes of the AST, the PHP-Parser library provides the `PhpParser\NodeFinder` class.

The documentation of PHP-Parser contains some examples of how to use the `NodeFinder`:  
[PHP-Parser/doc/component/Walking_the_AST#simple-node-finding](https://github.com/nikic/PHP-Parser/blob/master/doc/component/Walking_the_AST.markdown#simple-node-finding)

The following is an example in the context of a codemod.
It simply searches for the first function definition in the AST and renames the found function to "foobar":

```php
<?php

use Codeshift\AbstractCodemod;
use PhpParser\{Node, NodeFinder};

class YourManualCodemod extends AbstractCodemod {

    public function beforeTraversalTransform(array $statements): array {
        $nodeFinder = new NodeFinder();
        $functionNode = $nodeFinder->findFirstInstanceOf($statements, Node\Stmt\Function_::class);

        if ($functionNode != null) {
            $functionNode->name = new Node\Identifier('foobar');
        }

        return $statements;
    }
}

return YourManualCodemod;
```



Programmable API
================

While in most cases it will be sufficient to use the CLI, the library offers a few useful classes for manual execution of codemods.

Mainly these are:
* [`CodemodRunner`](https://github.com/Atanamo/PHP-Codeshift/blob/master/src/CodemodRunner.php)
* [`CodeTransformer`](https://github.com/Atanamo/PHP-Codeshift/blob/master/src/CodemodRunner.php)
* [`AbstractTracer`](https://github.com/Atanamo/PHP-Codeshift/blob/master/src/AbstractTracer.php)


CodemodRunner
-------------

The `CodemodRunner` is the most convienent way to execute one or more codemods by using their file names.

The following example shows how to use the `CodemodRunner` for executing 4 different codemods sequentially by an automated routine:

```php
<?php

$codemodPaths = [
    'path/to/my/codemod_1.php',
    'path/to/my/codemod_2.php',
    'path/to/my/codemod_3.php',
    'path/to/my/codemod_4.php'
];

$srcPath = 'my/project/src';
$outPath = 'my/project/transformed_src';
$ignorePaths = [
    'some/special/file_to_skip.php'
    'some/very/special/dir_to_skip'
];

// Execute the codemods
try {
    $runner = new \Codeshift\CodemodRunner();
    $runner->addCodemods($codemodPaths);
    $runner->execute($srcPath, $outPath, $ignorePaths);
}
catch (\Exception $ex) {
    echo "Error while executing codemods!\n";
    echo $ex->getMessage();
}
```

Please see the class documentation for further methods and details.


CodeTransformer
---------------

The `CodeTransformer` provides some more basic routines for executing a codemod.
It is used by the `CodemodRunner` internally.

The following example uses the `CodeTransformer` to transform code from a string that is fetched by a custom function.
```php
<?php

$codemod = new MySpecialCodemod();

$transformer = new \Codeshift\CodeTransformer();
$transformer->setCodemod($codemod);

// Store some PHP code to string
$codeString = getSomeCodeInput();

// Execute the codemod
try {
    echo $transformer->runOnCode($codeString);
}
catch (\Exception $ex) {
    echo "Error while executing code transformation!\n";
    echo $ex->getMessage();
}
```

Please see the class documentation for further methods and details.


AbstractTracer: Custom logging
------------------------------

If you use the API classes, they generate some logs to STDOUT by default.
For instance, the execution of a codemod is logged and each transformation of a file.

These logs are generated by the `SimpleOutputTracer`, which is the default implementation for the `AbstractTracer` class.

You can implement your own "Tracer" by deriving the `AbstractTracer` and providing it to the `CodemodRunner` or `CodeTransformer`.

This allows you to log processing steps to e.g. a special stream. You may also implement a kind of adapter by this, which provides information for continuous-integration frameworks or similar.


The following example defines an own "Tracer" and passes it to the `CodemodRunner`:
```php
<?php

class MySpecialTracer extends AbstractTracer {
    public function writeLine($text='') {
        echo '<p>', $text, '</p>';
    }
}

$runner = new \Codeshift\CodemodRunner('path/to/my/codemod.php', new MySpecialTracer());
$runner->executeSecured('my/project/src');
```

The custom tracer `MySpecialTracer` simply writes all output as HTML paragraphs.
Also note that the example uses `executeSecured` here, to even log exceptions by the custom tracer.

From a practical point of view, it might be more useful to override other methods with custom implementations.
Here are a few examples:

* `traceFileTransformation($inputFilePath, $outputFilePath, $fileChanged)`
* `traceCodemodLoaded($codemod, $codemodPath)`
* `traceException($exception, $withStackTrace)`

Please see the class documentation for further methods and details.

