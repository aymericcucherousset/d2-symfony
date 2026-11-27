<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * In-memory logger recording every message it receives.
 */
final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $logs = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
