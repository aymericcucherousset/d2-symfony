<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\CommonMark;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Format;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Renders ```` ```d2 ```` fenced code blocks to inline SVG.
 *
 * Other fenced code blocks are left to the next renderer (the default
 * `<pre><code>` one), as are empty `d2` blocks.
 */
final readonly class D2CodeBlockRenderer implements NodeRendererInterface
{
    public const LANGUAGE = 'd2';

    /**
     * @throws InvalidArgumentException when the options ask for another format than SVG
     */
    public function __construct(
        private Renderer $renderer,
        private RenderOptions $options = new RenderOptions(),
    ) {
        if (Format::Svg !== $options->format) {
            throw new InvalidArgumentException(\sprintf('Markdown d2 code blocks are rendered to inline SVG and cannot be rendered to %s.', strtoupper($options->format->value)));
        }
    }

    /**
     * @throws D2Exception
     * @throws RenderException when D2 rejects the diagram; the message gives the Markdown line
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (!$node instanceof FencedCode || !self::isD2Block($node) || '' === trim($node->getLiteral())) {
            return null;
        }

        try {
            $svg = $this->renderer->render(new Diagram($node->getLiteral(), $this->options));
        } catch (RenderException $e) {
            throw self::locate($e, $node->getStartLine());
        }

        // An XML declaration is not valid inside an HTML document; CommonMark separates blocks itself.
        return rtrim(preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg);
    }

    private static function isD2Block(FencedCode $node): bool
    {
        return self::LANGUAGE === strtolower($node->getInfoWords()[0] ?? '');
    }

    /**
     * Adds the Markdown line of the error to the message: Markdown documents usually hold several diagrams.
     */
    private static function locate(RenderException $e, ?int $fenceLine): RenderException
    {
        if (null === $fenceLine) {
            return $e;
        }

        $d2Line = $e->getD2Line();
        $location = null === $d2Line
            ? \sprintf('in the d2 code block at line %d of the Markdown document', $fenceLine)
            // The D2 source starts on the line after the opening fence.
            : \sprintf('at line %d of the Markdown document', $fenceLine + $d2Line);

        return new RenderException(\sprintf('%s (%s)', $e->getMessage(), $location), $e->getD2Error(), $e);
    }
}
