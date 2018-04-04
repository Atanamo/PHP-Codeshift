<?php

namespace Codeshift;

use \PhpParser\{NodeFinder, NodeTraverser, NodeVisitor};


abstract class AbstractCodemod {
    private $traversers = [];
    private $codeInfoMap = [];

    final public function __construct() {
        $this->init();
    }

    final public function transformStatements(array $statements) {
        $statements = $this->beforeTraversalTransform($statements);

        foreach ($this->traversers as $oTraverser) {
            $statements = $oTraverser->traverse($statements);
        }

        $statements = $this->afterTraversalTransform($statements);

        return $statements;
    }

    final public function setCodeInformation(array $infoMap) {
        if (empty($infoMap)) {
            $this->codeInfoMap = [];
        } else {
            $this->codeInfoMap = $infoMap;
        }
    }

    final public function getCodeInformation() {
        return $this->codeInfoMap;
    }


	final protected function createNodeTraverser(NodeVisitor ...$visitors) {
		$oTraverser = new NodeTraverser();

		foreach ($visitors as $oVisitor) {
			$oTraverser->addVisitor($oVisitor);
		}

		return $oTraverser;
	}

    final protected function addTraversalTransform(NodeVisitor ...$visitors) {
        $oTraverser = $this->createNodeTraverser(...$visitors);

        $this->traversers[] = $oTraverser;

        return $oTraverser;
    }

    final protected function clearTraversalTransforms() {
        $this->traversers = [];
    }


    # To be overridden
    public function init() {
    }

    # To be overridden
    public function beforeTraversalTransform(array $statements): array {
        return $statements;
    }

    # To be overridden
    public function afterTraversalTransform(array $statements): array {
        return $statements;
    }

}

