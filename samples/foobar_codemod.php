<?php

use Codeshift\AbstractCodemod;
use PhpParser\{Node, NodeFinder, NodeVisitorAbstract};


// Example visitor: Replace any string to "foo"
class FooReplaceVisitor extends NodeVisitorAbstract {

    public function leaveNode(Node $node) {
        if ($node instanceof Node\Scalar\String_) {
            $node->value = 'foo';
        }
    }
}


class FoobarCodemod extends AbstractCodemod {

    // Example: Traverse with visitor "FooReplaceVisitor"
    // @override
    public function init() {
        // Init the example visitor
        $visitor = new FooReplaceVisitor();

        // Schedule a traversal run on the code, that uses the visitor
        $this->addTraversalTransform($visitor);
    }

    // Example: Get information about current transformed code/file.
    // @override
    public function beforeTraversalTransform(array $statements): array {
        $infoMap = $this->getCodeInformation();

        $tracer = $this->getTracer();
        $tracer->writeLine();
        $tracer->inform("Will now transform: '{$infoMap['inputFile']}'");
        $tracer->inform("Will output to: '{$infoMap['outputFile']}'");
        $tracer->writeLine();

        return $statements;
    }

    // Example: Rename first declared function to "bar"
    // @override
    public function afterTraversalTransform(array $statements): array {
        $nodeFinder = new NodeFinder();
        $functionNode = $nodeFinder->findFirstInstanceOf($statements, Node\Stmt\Function_::class);

        if ($functionNode != null) {
            $functionNode->name = new Node\Identifier('bar');
        }

        return $statements;
    }

};


// Important: Export the codemod class
return FoobarCodemod;
