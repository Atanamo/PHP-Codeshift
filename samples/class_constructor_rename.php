<?php

use \Codeshift\AbstractCodemod;
use \PhpParser\{Node, NodeVisitorAbstract};


// Visitor, which renames old-style constructors of PHP <= 4 to the new style.
// Practically speaking, it searches for class methods named like their class.
class ConstructorRenamingVisitor extends NodeVisitorAbstract {
    private $currClassName = '';

    public function enterNode(Node $node) {
        if ($node instanceof Node\Stmt\Class_) {
            $identifier = $node->name;
            $this->currClassName = $identifier->name;  // Remember class name
        }
    }

    public function leaveNode(Node $node) {
        if ($node instanceof Node\Stmt\ClassMethod) {
            $identifier = $node->name;

            // Replace method name, if it matches class name
            if ($identifier == $this->currClassName AND $identifier != '') {
                $node->name = new Node\Identifier('__construct');
            }
        }
    }
}


// Codemod definition class
class ConstructorRenamingCodemod extends AbstractCodemod {

    // @override
    public function init() {
        // Init the renaming visitor
        $visitor = new ConstructorRenamingVisitor();

        // Schedule a traversal run on the code that uses the visitor
        $this->addTraversalTransform($visitor);
    }

};


// Important: Export the codemod class
return ConstructorRenamingCodemod;
