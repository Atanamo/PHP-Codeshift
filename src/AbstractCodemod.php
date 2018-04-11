<?php

namespace Codeshift;

use \PhpParser\{NodeFinder, NodeTraverser, NodeVisitor, Node\Stmt};


/**
 * Abstraction of a single codemod.
 * Provides the main API methods and utility methods to define code transformations.
 * 
 * One or more of the following hook methods can be overridden to define the transformations:
 * * {@see AbstractCodemod::init()}
 * * {@see AbstractCodemod::beforeTraversalTransform()}
 * * {@see AbstractCodemod::afterTraversalTransform()}
 * 
 * Basically, the complete code transformation (of one file) is performed in following steps:
 * 1. Calling `beforeTraversalTransform`
 * 2. Running traversal transformation by its substeps ({@see AbstractCodemod::addTraversalTransform()})
 * 3. Calling `afterTraversalTransform`
 * 
 * The most convenient way to execute the codemod is by using the {@see CodemodRunner}.
 * A more primitive way is the use of the {@see CodeTransformer}.
 */
abstract class AbstractCodemod {
    private $oTracer;
    private $traversers = [];
    private $codeInfoMap = [];

    /**
     * Constructor.
     * Optionally takes a tracer to be used for protocolling.
     *
     * @param AbstractTracer|null $oTracer The tacer object
     */
    final public function __construct(AbstractTracer $oTracer=null) {
        $this->oTracer = $oTracer;
        $this->init();
    }

    /**
     * Executes all defined transformation steps for the given statements.
     * Transformation steps have to be defined by traversal transform substeps 
     * and/or custom overrides of the before/after hooks.
     * 
     * @see AbstractCodemod::init()
     * @see AbstractCodemod::beforeTraversalTransform()
     * @see AbstractCodemod::afterTraversalTransform()
     *
     * @param Stmt[] $statements The list of statement nodes of the source file to transform
     * @return Stmt[] The resulting list of (transformed) statements
     */
    final public function transformStatements(array $statements) {
        $statements = $this->beforeTraversalTransform($statements);

        foreach ($this->traversers as $oTraverser) {
            $statements = $oTraverser->traverse($statements);
        }

        $statements = $this->afterTraversalTransform($statements);

        return $statements;
    }

    /**
     * Sets new information about the code to be transformed.
     * This allows to e.g. provide the related file names.
     *
     * @see AbstractCodemod::getCodeInformation()
     * 
     * @param array $infoMap List of named information attributes to set
     * @return void
     */
    final public function setCodeInformation(array $infoMap) {
        if (empty($infoMap)) {
            $this->codeInfoMap = [];
        } else {
            $this->codeInfoMap = $infoMap;
        }
    }

    /**
     * Returns a list of named attributes providing information about the code to transform.
     * 
     * The list has to be set by method `setCodeInformation` before.
     * If the codemod is executed by using the CLI or a `CodemodRunner`, the list is set automatically.
     * It then contains the following attributes:
     * * `inputFile`: The full path of the current source file of the code to transform.
     * * `outputFile`: The full path of the current output file for the transformed code.
     * 
     * *(These attributes are provided by almost all "run" methods of the `CodeTransformer`.
     *   Only if the codemod is executed manually by using `CodeTransformer::runOnCode()`,
     *   the list depends on the optional argument `$codeInfoMap` of that method.)*
     * 
     * Note:
     * The result information is only up-to-date while execution of transformations,
     * not while preparation of it (`init`).
     *
     * @return array The list of named information attributes
     */
    final public function getCodeInformation() {
        return $this->codeInfoMap;
    }

    /**
     * Returns the tracer object the codemod was initialized with or null if there is no tracer.
     * If the codemod is executed by using the CLI or a `CodemodRunner`, the tracer is always shared by it.
     *
     * @return AbstractTracer|null The set tracer object
     */
    final public function getTracer() {
        return $this->oTracer;
    }

    /**
     * Returns a new `NodeTraverser` object, having the given visitor objects added to it.
     * The traverser can be used to transform an AST (/statements) as defined by the visitors.
     *
     * @see https://github.com/nikic/PHP-Parser/blob/master/doc/component/Walking_the_AST.markdown
     * 
     * @param NodeVisitor ...$visitors One or more visitors to add to the traverser
     * @return NodeTraverser The resulting traverser object
     */
	final protected function createNodeTraverser(NodeVisitor ...$visitors) {
		$oTraverser = new NodeTraverser();

		foreach ($visitors as $oVisitor) {
			$oTraverser->addVisitor($oVisitor);
		}

		return $oTraverser;
	}

    /**
     * Adds a new traversal transform substep to be run automatically when executing the codemod (for a file).
     * This is done by adding a new `NodeTraverser` object, having the given visitor objects added to it.
     * 
     * The substeps are executed in the order they were added.
     * 
     * Hook method `beforeTraversalTransform` is executed before the first traversal transform substep / traverser.
     * Hook method `afterTraversalTransform` is executed after the last traversal transform substep / traverser.
     *
     * @see https://github.com/nikic/PHP-Parser/blob/master/doc/component/Walking_the_AST.markdown
     * @uses AbstractCodemod::createNodeTraverser()
     * 
     * @param NodeVisitor ...$visitors One or more visitors to add to the traverser
     * @return NodeTraverser The added traverser object
     */
    final protected function addTraversalTransform(NodeVisitor ...$visitors) {
        $oTraverser = $this->createNodeTraverser(...$visitors);

        $this->traversers[] = $oTraverser;

        return $oTraverser;
    }

    /**
     * Removes all traversal transform substeps from being executed automatically (for the next transformation or file).
     *
     * @see AbstractCodemod::addTraversalTransform()
     * @return void
     */
    final protected function clearTraversalTransforms() {
        $this->traversers = [];
    }


    /**
     * Hook: Override to define a custom initialization step.
     * 
     * Method is called on instantiation of the codemod, 
     * which happens once before executing it for any source directory or file.
     * 
     * Normally you would set up the traversal transformation here - 
     * by setting up one ore more `NodeTraverser` objects.
     * 
     * @see AbstractCodemod::createNodeTraverser()
     * @see AbstractCodemod::addTraversalTransform()
     * @see AbstractCodemod::clearTraversalTransforms()
     * 
     * @return void
     */
    public function init() {
    }

    /**
     * Hook: Override to define a custom transformation step, which should be executed before traversal transformation.
     * The traversal transformation requires a `NodeTraverser` to be set up.
     * (It's possible to set up the traverser in this "before hook", but normally you would do so in `init`).
     * 
     * Method is called once for each source file to transform.
     * 
     * The input statements are untouched / as read from the file.
     * The output statements are used to be passed to traversal transformation (if set up) 
     * or the `afterTraversalTransform` method.
     *
     * @param Stmt[] $statements The list of statement nodes of the source file to transform
     * @return Stmt[] The resulting list of (transformed) statements
     */
    public function beforeTraversalTransform(array $statements): array {
        return $statements;
    }

    /**
     * Hook: Override to define a custom transformation step, which should be executed after traversal transformation.
     * The traversal transformation requires a `NodeTraverser` to be set up before.
     * 
     * Method is called once for each source file to transform.
     * 
     * The input statements are the result of any custom or traversal transformation done before.
     * The output statements are used to write the resulting source file.
     *
     * @param Stmt[] $statements The list of statement nodes of the source file to transform
     * @return Stmt[] The resulting list of (transformed) statements
     */
    public function afterTraversalTransform(array $statements): array {
        return $statements;
    }

}

