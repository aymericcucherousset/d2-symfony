<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides the `|d2` filter (`{{ source|d2({ theme: 200, sketch: true }) }}`)
 * and the `{% d2 with { theme: 200 } %}…{% endd2 %}` tag.
 *
 * Rendering happens in the lazily loaded {@see D2Runtime}.
 */
final class D2Extension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('d2', [D2Runtime::class, 'render'], ['is_safe' => ['html']]),
        ];
    }

    public function getTokenParsers(): array
    {
        return [new D2TokenParser()];
    }
}
