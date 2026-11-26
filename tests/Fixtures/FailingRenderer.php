<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Fixtures;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Renderer;

/**
 * Renderer failing like d2 does, with a given raw error output.
 */
final readonly class FailingRenderer implements Renderer
{
    public function __construct(private string $d2Error)
    {
    }

    public function render(Diagram $diagram): string
    {
        throw RenderException::fromD2Error($this->d2Error);
    }
}
