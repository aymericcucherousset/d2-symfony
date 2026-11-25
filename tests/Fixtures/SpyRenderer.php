<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Fixtures;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Renderer;

/**
 * In-memory renderer recording every diagram it receives.
 */
final class SpyRenderer implements Renderer
{
    /** @var list<Diagram> */
    public array $diagrams = [];

    /**
     * @param non-empty-string $svg
     */
    public function __construct(private readonly string $svg = '<svg>rendered</svg>')
    {
    }

    public function render(Diagram $diagram): string
    {
        $this->diagrams[] = $diagram;

        return $this->svg;
    }
}
