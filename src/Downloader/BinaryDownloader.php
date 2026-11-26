<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Downloader;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Downloader\Exception\ChecksumMismatchException;
use Aymericcucherousset\D2Symfony\Downloader\Exception\DownloadException;
use Aymericcucherousset\D2Symfony\Downloader\Exception\UnsupportedPlatformException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads the d2 binary published on the terrastruct/d2 GitHub releases,
 * verifies its SHA-256 checksum and installs it in "<installDir>/<version>/d2".
 */
final class BinaryDownloader
{
    /** Version tested in CI and installed by default. */
    public const DEFAULT_VERSION = 'v0.9.0';
    public const DEFAULT_BASE_URL = 'https://github.com/terrastruct/d2/releases/download';

    private readonly string $version;
    private ?Platform $platform;
    private ?HttpClientInterface $client;

    public function __construct(
        private readonly string $installDir,
        string $version = self::DEFAULT_VERSION,
        ?HttpClientInterface $client = null,
        ?Platform $platform = null,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
        if (1 !== preg_match('/^v?(\d+\.\d+\.\d+)$/', $version, $matches)) {
            throw new InvalidArgumentException(\sprintf('The d2 version must look like "v0.9.0", "%s" given.', $version));
        }

        $this->version = 'v'.$matches[1];
        $this->client = $client;
        $this->platform = $platform;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @throws UnsupportedPlatformException
     */
    public function getPlatform(): Platform
    {
        return $this->platform ??= Platform::detect();
    }

    /**
     * Returns where the binary is (or will be) installed.
     *
     * @throws UnsupportedPlatformException
     */
    public function getBinaryPath(): string
    {
        return $this->versionDir().\DIRECTORY_SEPARATOR.$this->getPlatform()->binaryName();
    }

    public function isInstalled(): bool
    {
        try {
            $path = $this->getBinaryPath();
        } catch (UnsupportedPlatformException) {
            return false;
        }

        return is_file($path) && is_executable($path);
    }

    /**
     * Downloads and installs the binary, unless it is already installed.
     *
     * @param bool $force download again even if the binary is already installed
     *
     * @return string the path of the installed binary
     *
     * @throws DownloadException
     * @throws UnsupportedPlatformException
     */
    public function download(bool $force = false): string
    {
        $binaryPath = $this->getBinaryPath();
        if (!$force && $this->isInstalled()) {
            return $binaryPath;
        }

        $platform = $this->getPlatform();
        $asset = $platform->assetName($this->version);
        $expectedChecksum = $this->fetchChecksum($asset);

        $dir = $this->versionDir();
        if (!is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new DownloadException(\sprintf('Unable to create the directory "%s".', $dir));
        }

        $archive = $dir.\DIRECTORY_SEPARATOR.'.download-'.bin2hex(random_bytes(6)).'.tar.gz';
        $extractDir = $archive.'.extracted';

        try {
            $this->downloadTo($this->url($asset), $archive);

            $actualChecksum = hash_file('sha256', $archive);
            if (false === $actualChecksum || !hash_equals($expectedChecksum, $actualChecksum)) {
                throw new ChecksumMismatchException(\sprintf('Checksum mismatch for "%s": expected %s, got %s. The download was aborted and nothing was installed.', $asset, $expectedChecksum, $actualChecksum ?: 'none'));
            }

            $entry = $platform->archiveEntry($this->version);
            try {
                (new \PharData($archive))->extractTo($extractDir, $entry, true);
            } catch (\Exception $e) {
                throw new DownloadException(\sprintf('Unable to extract "%s" from "%s": %s', $entry, $asset, $e->getMessage()), 0, $e);
            }

            $extracted = $extractDir.\DIRECTORY_SEPARATOR.$entry;
            if (!is_file($extracted) || !@rename($extracted, $binaryPath) || !@chmod($binaryPath, 0o755)) {
                throw new DownloadException(\sprintf('Unable to install the d2 binary to "%s".', $binaryPath));
            }
        } finally {
            if (is_file($archive)) {
                @unlink($archive);
            }
            self::removeDirectory($extractDir);
        }

        return $binaryPath;
    }

    private function fetchChecksum(string $asset): string
    {
        $url = $this->url('SHA256SUMS');

        try {
            $checksums = $this->client()->request('GET', $url)->getContent();
        } catch (HttpClientException $e) {
            throw new DownloadException(\sprintf('Unable to download the d2 checksums from "%s": %s', $url, $e->getMessage()), 0, $e);
        }

        foreach (preg_split('/\R/', $checksums) ?: [] as $line) {
            if (1 === preg_match('/^([a-f0-9]{64})\s+\*?(\S+)$/i', trim($line), $matches) && $matches[2] === $asset) {
                return strtolower($matches[1]);
            }
        }

        throw new DownloadException(\sprintf('No checksum is published for "%s" in "%s"; this d2 version may not support your platform.', $asset, $url));
    }

    private function downloadTo(string $url, string $path): void
    {
        $handle = fopen($path, 'w');
        if (false === $handle) {
            throw new DownloadException(\sprintf('Unable to write "%s".', $path));
        }

        try {
            $response = $this->client()->request('GET', $url);
            if (200 !== $status = $response->getStatusCode()) {
                throw new DownloadException(\sprintf('Unable to download "%s": HTTP %d.', $url, $status));
            }

            foreach ($this->client()->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } catch (HttpClientException $e) {
            throw new DownloadException(\sprintf('Unable to download "%s": %s', $url, $e->getMessage()), 0, $e);
        } finally {
            fclose($handle);
        }
    }

    private function url(string $file): string
    {
        return rtrim($this->baseUrl, '/').'/'.$this->version.'/'.$file;
    }

    private function versionDir(): string
    {
        return rtrim($this->installDir, '/\\').\DIRECTORY_SEPARATOR.$this->version;
    }

    private function client(): HttpClientInterface
    {
        return $this->client ??= HttpClient::create();
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }
}
