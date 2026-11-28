<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig;

use Aymericcucherousset\D2Symfony\Twig\NodeVisitor\PrerenderStaticDiagramsNodeVisitor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Provides the `|d2` filter (`{{ source|d2({ theme: 200, sketch: true }) }}`)
 * the `{% d2 with { theme: 200 } %}…{% endd2 %}` tag and the
 * `{{ d2_file('diagrams/architecture.d2', { theme: 200 }) }}` function.
 *
 * Rendering happens in the lazily loaded {@see D2Runtime}.
 */
final class D2Extension extends AbstractExtension
{
    /**
     * @param bool $prerenderStaticDiagrams render `{% d2 %}` tags with a constant body when templates are compiled
     */
    public function __construct(private readonly bool $prerenderStaticDiagrams = false)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('d2', [D2Runtime::class, 'render'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('d2_file', [D2Runtime::class, 'renderFile'], ['is_safe' => ['html']]),
        ];
    }

    public function getTokenParsers(): array
    {
        return [new D2TokenParser()];
    }

    public function getNodeVisitors(): array
    {
        return $this->prerenderStaticDiagrams ? [new PrerenderStaticDiagramsNodeVisitor()] : [];
    }
}
