<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Fixtures;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\BinaryNotFoundException;
use Aymericcucherousset\D2Symfony\Core\VersionAwareRenderer;

/**
 * Renderer whose d2 binary is missing, counting its calls.
 */
final class UnavailableRenderer implements VersionAwareRenderer
{
    public int $renderCalls = 0;
    public int $versionCalls = 0;

    public function __construct(private readonly string $binary = 'missing-d2')
    {
    }

    public function render(Diagram $diagram): string
    {
        ++$this->renderCalls;

        throw new BinaryNotFoundException($this->binary);
    }

    public function getVersion(): string
    {
        ++$this->versionCalls;

        throw new BinaryNotFoundException($this->binary);
    }
}
