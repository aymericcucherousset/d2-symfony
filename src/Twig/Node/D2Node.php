<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig\Node;

use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\CaptureNode;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

/**
 * Renders the captured body of a `{% d2 %}` tag through {@see D2Runtime}.
 */
#[YieldReady]
final class D2Node extends Node
{
    /**
     * @param int $bodyLine template line where the D2 source starts
     */
    public function __construct(Node $body, ?AbstractExpression $options, int $lineno, int $bodyLine)
    {
        $nodes = ['body' => $body];
        if (null !== $options) {
            $nodes['options'] = $options;
        }

        parent::__construct($nodes, ['body_line' => $bodyLine], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $source = $compiler->getVarName();

        $capture = new CaptureNode($this->getNode('body'), $this->getTemplateLine());
        $capture->setAttribute('raw', true);

        $compiler
            ->addDebugInfo($this)
            ->write(\sprintf('$%s = ', $source))
            ->subcompile($capture)
            ->raw("\n")
            // Map rendering errors to the tag line rather than to the last line of the body.
            ->addDebugInfo($this)
            ->write($compiler->getEnvironment()->useYield() ? 'yield ' : 'echo ')
            ->raw('$this->env->getRuntime(')
            ->string(D2Runtime::class)
            ->raw(\sprintf(')->renderTag($%s, ', $source))
        ;

        if ($this->hasNode('options')) {
            $compiler->subcompile($this->getNode('options'));
        } else {
            $compiler->raw('[]');
        }

        $compiler->raw(', ')->repr($this->getAttribute('body_line'))->raw(");\n");
    }
}
