<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Checks that the d2 binary is available for integration tests.
 */
final class D2BinaryTest extends TestCase
{
    use RequiresD2Binary;

    public function testD2BinaryReportsItsVersion(): void
    {
        self::requireD2Binary();

        $process = new Process(['d2', '--version']);
        $process->mustRun();

        self::assertMatchesRegularExpression('/^v?\d+\.\d+\.\d+/', trim($process->getOutput()));
    }
}
