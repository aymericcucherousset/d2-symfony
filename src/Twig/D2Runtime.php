<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Format;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Twig\Error\RuntimeError;
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
     * @param array<array-key, mixed> $options overrides of the default options, see {@see RenderOptions}
     *
     * @throws D2Exception
     * @throws InvalidArgumentException when the options ask for another format than SVG
     */
    public function render(string|\Stringable $source, array $options = []): string
    {
        $options = $this->defaults->merge($options);
        if (Format::Svg !== $options->format) {
            throw new InvalidArgumentException(\sprintf('The "d2" filter and tag output inline SVG and cannot render to %s; use the "d2_data_uri" filter instead.', strtoupper($options->format->value)));
        }

        $svg = $this->renderer->render(new Diagram((string) $source, $options));

        // An XML declaration is not valid inside an HTML document.
        return preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }

    /**
     * Renders D2 source to a base64 data URI, PNG by default (e.g. for PDF generators and emails).
     *
     * @param array<array-key, mixed> $options overrides of the default options, see {@see RenderOptions};
     *                                         "format" selects SVG, PNG (default, also when null) or PDF
     *
     * @throws D2Exception
     */
    public function dataUri(string|\Stringable $source, array $options = []): string
    {
        // A null format means "the default" here too, which is PNG for data URIs.
        $options = $this->defaults->merge([...$options, 'format' => $options['format'] ?? Format::Png]);

        return 'data:'.$options->format->mimeType().';base64,'.base64_encode($this->renderer->render(new Diagram((string) $source, $options)));
    }

    /**
     * Renders the body of a `{% d2 %}` tag; D2 errors point to the matching template line.
     *
     * @internal called by the code compiled from {@see Node\D2Node}
     *
     * @param array<array-key, mixed> $options
     * @param int                     $bodyLine template line of the first line of the D2 source
     *
     * @throws D2Exception
     * @throws RuntimeError when D2 reports an error at a known line
     */
    public function renderTag(string|\Stringable $source, array $options, int $bodyLine): string
    {
        try {
            return $this->render($source, $options);
        } catch (RenderException $e) {
            $d2Line = $e->getD2Line();
            if (null === $d2Line) {
                throw $e;
            }

            // Exact for static bodies; Twig expressions printing several lines shift the following ones.
            throw new RuntimeError($e->getMessage(), $bodyLine + $d2Line - 1, null, $e);
        }
    }
}
