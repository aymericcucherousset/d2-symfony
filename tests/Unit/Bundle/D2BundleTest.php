<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Bundle;

use Aymericcucherousset\D2Symfony\Bundle\D2Bundle;
use PHPUnit\Framework\TestCase;

final class D2BundleTest extends TestCase
{
    public function testBundlePathIsPackageRoot(): void
    {
        $bundle = new D2Bundle();

        self::assertSame(\dirname(__DIR__, 3), $bundle->getPath());
    }
}
