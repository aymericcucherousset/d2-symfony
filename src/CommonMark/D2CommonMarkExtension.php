<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\CommonMark;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * league/commonmark extension rendering ```` ```d2 ```` code blocks to inline SVG.
 *
 * ```php
 * $converter = new CommonMarkConverter();
 * $converter->getEnvironment()->addExtension(new D2CommonMarkExtension($renderer));
 * ```
 *
 * With the bundle and twig/extra-bundle, it is registered automatically for the
 * `|markdown_to_html` Twig filter.
 */
final readonly class D2CommonMarkExtension implements ExtensionInterface
{
    /** Higher than the default fenced code renderer (0), which renders the other blocks. */
    private const PRIORITY = 10;

    private D2CodeBlockRenderer $codeBlockRenderer;

    /**
     * @param RenderOptions $options options of every diagram
     *
     * @throws InvalidArgumentException when the options ask for another format than SVG
     */
    public function __construct(Renderer $renderer, RenderOptions $options = new RenderOptions())
    {
        $this->codeBlockRenderer = new D2CodeBlockRenderer($renderer, $options);
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(FencedCode::class, $this->codeBlockRenderer, self::PRIORITY);
    }
}
