<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration\Downloader;

use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Downloader\BinaryDownloader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class RealDownloadTest extends TestCase
{
    public function testDefaultVersionIsTheOneTestedInCi(): void
    {
        $ciVersion = getenv('D2_VERSION');
        if (false === $ciVersion || '' === $ciVersion) {
            self::markTestSkipped('D2_VERSION is only set in CI.');
        }

        self::assertSame($ciVersion, BinaryDownloader::DEFAULT_VERSION, 'Update BinaryDownloader::DEFAULT_VERSION together with D2_VERSION in the CI workflow.');
    }

    /**
     * Downloads the real binary from GitHub (about 18 MB): only run with D2_DOWNLOAD_TEST=1.
     */
    public function testItDownloadsTheRealBinary(): void
    {
        if ('1' !== getenv('D2_DOWNLOAD_TEST')) {
            self::markTestSkipped('Set D2_DOWNLOAD_TEST=1 to download the real d2 binary.');
        }

        $dir = sys_get_temp_dir().'/d2-real-download-'.bin2hex(random_bytes(6));

        try {
            $path = (new BinaryDownloader($dir))->download();

            self::assertSame(BinaryDownloader::DEFAULT_VERSION, (new BinaryRenderer($path))->getVersion());
        } finally {
            (new Filesystem())->remove($dir);
        }
    }
}
