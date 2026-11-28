<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig;

use Aymericcucherousset\D2Symfony\Twig\NodeVisitor\PrerenderStaticDiagramsNodeVisitor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides the `|d2` filter (`{{ source|d2({ theme: 200, sketch: true }) }}`),
 * the `{% d2 with { theme: 200 } %}…{% endd2 %}` tag, and the `|d2_data_uri` filter
 * (`<img src="{{ source|d2_data_uri }}">`, PNG by default).
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
            // Only base64 characters: nothing to escape in HTML text or quoted attributes.
            new TwigFilter('d2_data_uri', [D2Runtime::class, 'dataUri'], ['is_safe' => ['html']]),
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
