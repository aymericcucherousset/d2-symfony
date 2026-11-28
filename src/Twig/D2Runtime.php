<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Twig\Error\RuntimeError;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class D2Runtime implements RuntimeExtensionInterface
{
    public function __construct(
        private Renderer $renderer,
        private RenderOptions $defaults = new RenderOptions(),
        private ?D2FileLoader $files = null,
    ) {
    }

    /**
     * Renders D2 source to inline SVG markup.
     *
     * @param array<array-key, mixed> $options overrides of the default options, see {@see RenderOptions}
     *
     * @throws D2Exception
     */
    public function render(string|\Stringable $source, array $options = []): string
    {
        $svg = $this->renderer->render(new Diagram((string) $source, $this->defaults->merge($options)));

        // An XML declaration is not valid inside an HTML document.
        return preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }

    /**
     * Renders a `.d2` file, relative to the configured root directory, to inline SVG markup.
     *
     * The file is read on each call; rendered SVG is cached by content, so editing the file
     * renders it again.
     *
     * @param array<array-key, mixed> $options overrides of the default options, see {@see RenderOptions}
     *
     * @throws D2Exception
     * @throws RuntimeError when D2 rejects the file
     */
    public function renderFile(string|\Stringable $path, array $options = []): string
    {
        if (null === $this->files) {
            throw new InvalidArgumentException('The d2_file() function requires a root directory: pass a D2FileLoader to the D2Runtime.');
        }

        $path = (string) $path;

        try {
            return $this->render($this->files->load($path), $options);
        } catch (RenderException $e) {
            // Tell which file the D2 line refers to; Twig adds the template and line of the call.
            throw new RuntimeError(\sprintf('%s (in D2 file "%s")', $e->getMessage(), $path), -1, null, $e);
        }
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
