<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Fixtures\Bundle;

use Aymericcucherousset\D2Symfony\Core\Renderer;

/**
 * Application service depending on the Renderer port, wired through autowiring.
 */
final readonly class RendererConsumer
{
    public function __construct(public Renderer $renderer)
    {
    }
}
