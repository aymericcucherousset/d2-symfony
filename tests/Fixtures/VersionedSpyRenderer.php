<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Fixtures;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\VersionAwareRenderer;

/**
 * In-memory versioned renderer counting its calls.
 */
final class VersionedSpyRenderer implements VersionAwareRenderer
{
    public int $renderCalls = 0;
    public int $versionCalls = 0;
    public bool $fail = false;

    public function __construct(public string $version = 'v1.0.0')
    {
    }

    public function render(Diagram $diagram): string
    {
        ++$this->renderCalls;

        if ($this->fail) {
            throw RenderException::fromD2Error('boom');
        }

        return \sprintf('<svg data-version="%s">%s</svg>', $this->version, $diagram->source);
    }

    public function getVersion(): string
    {
        ++$this->versionCalls;

        return $this->version;
    }
}
