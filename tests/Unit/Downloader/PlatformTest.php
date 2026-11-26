<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Downloader;

use Aymericcucherousset\D2Symfony\Downloader\Exception\UnsupportedPlatformException;
use Aymericcucherousset\D2Symfony\Downloader\Platform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatformTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function supportedPlatformProvider(): iterable
    {
        $machines = ['amd64' => ['x86_64', 'amd64', 'AMD64', 'x64'], 'arm64' => ['aarch64', 'arm64', 'ARM64', 'armv8']];
        $systems = ['Linux' => 'linux', 'Darwin' => 'macos', 'Windows' => 'windows'];

        foreach ($systems as $family => $os) {
            foreach ($machines as $arch => $aliases) {
                foreach ($aliases as $machine) {
                    yield \sprintf('%s %s', $family, $machine) => [$family, $machine, $os, $arch];
                }
            }
        }
    }

    #[DataProvider('supportedPlatformProvider')]
    public function testSupportedPlatformsAreDetected(string $family, string $machine, string $os, string $arch): void
    {
        $platform = Platform::detect($family, $machine);

        self::assertSame($os, $platform->os);
        self::assertSame($arch, $platform->arch);
        self::assertSame(\sprintf('d2-v0.9.0-%s-%s.tar.gz', $os, $arch), $platform->assetName('v0.9.0'));
        self::assertSame($os.'-'.$arch, (string) $platform);
    }

    /**
     * @return iterable<string, array{'linux'|'macos'|'windows', string, string}>
     */
    public static function binaryNameProvider(): iterable
    {
        yield 'linux' => ['linux', 'd2', 'd2-v0.9.0/bin/d2'];
        yield 'macos' => ['macos', 'd2', 'd2-v0.9.0/bin/d2'];
        yield 'windows' => ['windows', 'd2.exe', 'd2-v0.9.0/bin/d2.exe'];
    }

    /**
     * @param 'linux'|'macos'|'windows' $os
     */
    #[DataProvider('binaryNameProvider')]
    public function testBinaryNameAndArchiveEntry(string $os, string $binary, string $entry): void
    {
        $platform = new Platform($os, 'amd64');

        self::assertSame($binary, $platform->binaryName());
        self::assertSame($entry, $platform->archiveEntry('v0.9.0'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedPlatformProvider(): iterable
    {
        yield 'BSD' => ['BSD', 'x86_64'];
        yield 'Solaris' => ['Solaris', 'x86_64'];
        yield 'unknown OS' => ['Unknown', 'x86_64'];
        yield '32-bit x86' => ['Linux', 'i686'];
        yield '32-bit ARM' => ['Linux', 'armv7l'];
        yield 'PowerPC' => ['Linux', 'ppc64le'];
    }

    #[DataProvider('unsupportedPlatformProvider')]
    public function testUnsupportedPlatformsAreRejected(string $family, string $machine): void
    {
        $this->expectException(UnsupportedPlatformException::class);
        $this->expectExceptionMessage(\sprintf('(%s %s)', $family, $machine));

        Platform::detect($family, $machine);
    }

    public function testCurrentPlatformCanBeDetected(): void
    {
        try {
            $platform = Platform::detect();
        } catch (UnsupportedPlatformException $e) {
            self::markTestSkipped($e->getMessage());
        }

        self::assertContains($platform->os, ['linux', 'macos', 'windows']);
    }
}
