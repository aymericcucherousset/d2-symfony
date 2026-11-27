<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\NoRendererAvailableException;
use Aymericcucherousset\D2Symfony\Core\Exception\RendererUnavailableException;

/**
 * Tries renderers in order (e.g. the local binary, then Kroki).
 *
 * The next renderer is only tried when the current one is unavailable
 * ({@see RendererUnavailableException}: missing binary, unreachable server…).
 * Other errors, such as D2 syntax errors, would fail with every renderer and are thrown immediately.
 */
final class ChainRenderer implements VersionAwareRenderer
{
    /** @var non-empty-list<Renderer> */
    private readonly array $renderers;

    /**
     * @param iterable<Renderer> $renderers
     */
    public function __construct(iterable $renderers)
    {
        $list = array_values([...$renderers]);

        if ([] === $list) {
            throw new InvalidArgumentException('The renderer chain must contain at least one renderer.');
        }

        $this->renderers = $list;
    }

    /**
     * @throws NoRendererAvailableException when every renderer is unavailable
     */
    public function render(Diagram $diagram): string
    {
        $failures = [];

        foreach ($this->renderers as $renderer) {
            try {
                return $renderer->render($diagram);
            } catch (RendererUnavailableException $e) {
                $failures[] = $e;
            }
        }

        throw new NoRendererAvailableException($failures);
    }

    /**
     * Returns the version of the first available renderer, which is the one {@see render()} uses,
     * or an empty string when that renderer does not expose a version.
     *
     * @throws NoRendererAvailableException when every renderer is unavailable
     */
    public function getVersion(): string
    {
        $failures = [];

        foreach ($this->renderers as $renderer) {
            if (!$renderer instanceof VersionAwareRenderer) {
                return '';
            }

            try {
                return $renderer->getVersion();
            } catch (RendererUnavailableException $e) {
                $failures[] = $e;
            }
        }

        throw new NoRendererAvailableException($failures);
    }
}
