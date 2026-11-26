<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Downloader;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Downloader\BinaryDownloader;
use Aymericcucherousset\D2Symfony\Downloader\Exception\ChecksumMismatchException;
use Aymericcucherousset\D2Symfony\Downloader\Exception\DownloadException;
use Aymericcucherousset\D2Symfony\Downloader\Platform;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FakeD2Release;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;

final class BinaryDownloaderTest extends TestCase
{
    private string $dir;
    private FakeD2Release $release;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/d2-downloader-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->release = new FakeD2Release($this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testItDownloadsVerifiesAndInstallsTheBinary(): void
    {
        $downloader = $this->downloader($this->release->client());

        self::assertFalse($downloader->isInstalled());
        $path = $downloader->download();

        self::assertSame($this->dir.'/install/v9.9.9/d2', $path);
        self::assertSame($path, $downloader->getBinaryPath());
        self::assertSame(FakeD2Release::BINARY, file_get_contents($path));
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            self::assertTrue(is_executable($path));
        }
        self::assertTrue($downloader->isInstalled());
        self::assertSame([
            'https://example.com/releases/v9.9.9/SHA256SUMS',
            'https://example.com/releases/v9.9.9/'.FakeD2Release::ASSET,
        ], $this->release->requestedUrls);
        self::assertSame(['d2'], self::filesIn($this->dir.'/install/v9.9.9'), 'No temporary file is left.');
    }

    public function testInstalledBinaryIsNotDownloadedAgain(): void
    {
        $this->downloader($this->release->client())->download();

        $path = $this->downloader(new MockHttpClient([]))->download();

        self::assertSame($this->dir.'/install/v9.9.9/d2', $path);
    }

    public function testForceDownloadsAgain(): void
    {
        $this->downloader($this->release->client())->download();
        file_put_contents($this->dir.'/install/v9.9.9/d2', 'outdated');

        $path = $this->downloader($this->release->client())->download(force: true);

        self::assertSame(FakeD2Release::BINARY, file_get_contents($path));
    }

    public function testInvalidChecksumAbortsTheInstallation(): void
    {
        $downloader = $this->downloader($this->release->client(checksum: str_repeat('a', 64)));

        try {
            $downloader->download();
            self::fail('A ChecksumMismatchException was expected.');
        } catch (ChecksumMismatchException $e) {
            self::assertStringContainsString('Checksum mismatch for "'.FakeD2Release::ASSET.'": expected '.str_repeat('a', 64).', got '.hash('sha256', $this->release->archive), $e->getMessage());
            self::assertStringContainsString('nothing was installed', $e->getMessage());
        }

        self::assertFalse($downloader->isInstalled());
        self::assertSame([], self::filesIn($this->dir.'/install/v9.9.9'), 'The downloaded archive is removed.');
    }

    public function testMissingChecksumIsAnError(): void
    {
        $this->expectException(DownloadException::class);
        $this->expectExceptionMessage('No checksum is published for "'.FakeD2Release::ASSET.'"');

        $this->downloader($this->release->client(checksums: str_repeat('0', 64)."  other.tar.gz\n"))->download();
    }

    public function testHttpErrorIsAnError(): void
    {
        $this->expectException(DownloadException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->downloader($this->release->client(status: 404))->download();
    }

    public function testArchiveWithoutTheBinaryIsAnError(): void
    {
        $release = new FakeD2Release($this->dir, 'd2-v9.9.9/README.md');

        $this->expectException(DownloadException::class);
        $this->expectExceptionMessage('d2-v9.9.9/bin/d2');

        $this->downloader($release->client())->download();
    }

    public function testVersionIsNormalized(): void
    {
        $downloader = new BinaryDownloader($this->dir, '0.9.0', platform: new Platform('linux', 'amd64'));

        self::assertSame('v0.9.0', $downloader->getVersion());
        self::assertSame($this->dir.'/v0.9.0/d2', $downloader->getBinaryPath());
    }

    public function testInvalidVersionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BinaryDownloader($this->dir, 'latest');
    }

    private function downloader(MockHttpClient $client): BinaryDownloader
    {
        return new BinaryDownloader($this->dir.'/install', FakeD2Release::VERSION, $client, new Platform('linux', 'amd64'), 'https://example.com/releases/');
    }

    /**
     * @return list<string>
     */
    private static function filesIn(string $dir): array
    {
        return is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..'])) : [];
    }
}
