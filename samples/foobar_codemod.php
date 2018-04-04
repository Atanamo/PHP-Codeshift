<?php

use PhpParser\{Node, NodeFinder, NodeVisitorAbstract};


// Example visitor: Replace any string to "foo"
class FooReplaceVisitor extends NodeVisitorAbstract {

    public function leaveNode(Node $node) {
        if ($node instanceof Node\Scalar\String_) {
            $node->value = 'foo';
        }
    }
}


// Interface function for transformations using visitors
function getModificationVisitors() {
    $visitor = new FooReplaceVisitor();

    return [$visitor];
}

// Interface function for simple manual transformations
function getManuallyTransformedStatements($statements, NodeFinder $nodeFinder) {
    // Example: Rename first declared function to "bar"
    $functionNode = $nodeFinder->findFirstInstanceOf($statements, Node\Stmt\Function_::class);

    if ($functionNode != null) {
        $functionNode->name = new Node\Identifier('bar');
    }

    return $statements;
}


