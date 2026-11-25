<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class D2Runtime implements RuntimeExtensionInterface
{
    public function __construct(
        private Renderer $renderer,
        private RenderOptions $defaults = new RenderOptions(),
    ) {
    }

    /**
     * Renders D2 source to inline SVG markup.
     *
     * @param array<array-key, mixed> $options overrides of the default options ("theme", "layout", "pad", "sketch")
     *
     * @throws D2Exception
     */
    public function render(string|\Stringable $source, array $options = []): string
    {
        $svg = $this->renderer->render(new Diagram((string) $source, $this->defaults->merge($options)));

        // An XML declaration is not valid inside an HTML document.
        return preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }
}
