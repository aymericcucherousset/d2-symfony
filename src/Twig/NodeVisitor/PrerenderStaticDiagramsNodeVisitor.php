<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig\NodeVisitor;

use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use Aymericcucherousset\D2Symfony\Twig\Node\D2Node;
use Twig\Environment;
use Twig\Node\AutoEscapeNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Renders `{% d2 %}` tags whose body and options are constant when the template is compiled,
 * and replaces them by the resulting SVG.
 *
 * As templates are compiled during `cache:warmup`, static diagrams then never call the renderer
 * at runtime. Diagrams that fail to render at compile time (syntax error, missing binary…) are
 * left untouched and rendered at runtime, where errors point to the template line.
 */
final class PrerenderStaticDiagramsNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof D2Node) {
            return $node;
        }

        $source = self::staticSource($node);
        $options = self::staticOptions($node);
        if (null === $source || null === $options) {
            return $node;
        }

        try {
            $svg = $env->getRuntime(D2Runtime::class)->render($source, $options);
        } catch (D2Exception) {
            return $node;
        }

        return new TextNode($svg, $node->getTemplateLine());
    }

    public function getPriority(): int
    {
        return 0;
    }

    private static function staticSource(D2Node $node): ?string
    {
        $body = $node->getNode('body');
        if ($body instanceof AutoEscapeNode) {
            $body = $body->getNode('body');
        }

        if ($body instanceof TextNode) {
            return self::text($body);
        }

        $source = '';
        foreach ($body as $child) {
            if (!$child instanceof TextNode) {
                return null;
            }
            $source .= self::text($child);
        }

        return $source;
    }

    private static function text(TextNode $node): string
    {
        $data = $node->getAttribute('data');

        return \is_string($data) ? $data : '';
    }

    /**
     * @return array<array-key, mixed>|null the options, or null when they are not constant
     */
    private static function staticOptions(D2Node $node): ?array
    {
        if (!$node->hasNode('options')) {
            return [];
        }

        $expression = $node->getNode('options');
        if (!$expression instanceof ArrayExpression) {
            return null;
        }

        $options = [];
        foreach ($expression->getKeyValuePairs() as $pair) {
            $key = \is_array($pair) ? ($pair['key'] ?? null) : null;
            $value = \is_array($pair) ? ($pair['value'] ?? null) : null;
            if (!$key instanceof ConstantExpression || !$value instanceof ConstantExpression) {
                return null;
            }

            $name = $key->getAttribute('value');
            if (!\is_string($name) && !\is_int($name)) {
                return null;
            }

            $options[$name] = $value->getAttribute('value');
        }

        return $options;
    }
}
