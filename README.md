
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

3. Execute the Codeshift CLI:

    ```text
    php vendor/bin/codeshift --help
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
Therefor, to simplify finding specific nodes of the AST, the PHP-Parser library provides the `PhpParser\NodeFinder`.

The documentation of PHP-Parser provides some examples of how to use the `NodeFinder`:  
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



.    
.    
.    
More to come...
