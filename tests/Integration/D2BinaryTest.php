<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Checks that the d2 binary is available for integration tests.
 *
 * Skipped when the binary is missing, unless the D2_REQUIRED environment
 * variable is set (as in CI), in which case the test fails.
 */
final class D2BinaryTest extends TestCase
{
    public function testD2BinaryReportsItsVersion(): void
    {
        $output = [];
        $exitCode = 0;
        exec('d2 --version 2>&1', $output, $exitCode);

        if (0 !== $exitCode) {
            if (false !== getenv('D2_REQUIRED') && '' !== getenv('D2_REQUIRED')) {
                self::fail('The d2 binary is required (D2_REQUIRED is set) but could not be executed.');
            }

            self::markTestSkipped('The d2 binary is not installed.');
        }

        self::assertMatchesRegularExpression('/^v?\d+\.\d+\.\d+/', trim(implode("\n", $output)));
    }
}
