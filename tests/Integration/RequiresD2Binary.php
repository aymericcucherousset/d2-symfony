<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration;

use Symfony\Component\Process\ExecutableFinder;

/**
 * Skips the test when the d2 binary is missing, unless the D2_REQUIRED
 * environment variable is set (as in CI), in which case the test fails.
 */
trait RequiresD2Binary
{
    private static function requireD2Binary(): void
    {
        if (null !== (new ExecutableFinder())->find('d2')) {
            return;
        }

        if (false !== getenv('D2_REQUIRED') && '' !== getenv('D2_REQUIRED')) {
            self::fail('The d2 binary is required (D2_REQUIRED is set) but could not be found in the PATH.');
        }

        self::markTestSkipped('The d2 binary is not installed.');
    }
}
