<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Fixtures;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Builds a fake terrastruct/d2 release (archive + SHA256SUMS) served by a MockHttpClient.
 */
final class FakeD2Release
{
    public const VERSION = 'v9.9.9';
    public const ASSET = 'd2-v9.9.9-linux-amd64.tar.gz';
    public const BINARY = "#!/bin/sh\necho v9.9.9\n";

    /** @var list<string> */
    public array $requestedUrls = [];

    public readonly string $archive;

    public function __construct(string $workDir, string $entry = 'd2-v9.9.9/bin/d2')
    {
        $tar = $workDir.'/release-'.bin2hex(random_bytes(4)).'.tar';
        $phar = new \PharData($tar);
        $phar->addFromString($entry, self::BINARY);
        $phar->compress(\Phar::GZ);
        unset($phar);

        $archive = file_get_contents($tar.'.gz');
        unlink($tar);
        unlink($tar.'.gz');
        if (!\is_string($archive)) {
            throw new \RuntimeException('Unable to build the fake d2 release.');
        }
        $this->archive = $archive;
    }

    /**
     * @param string|null $checksum checksum to publish (defaults to the real one)
     * @param int         $status   HTTP status of the archive download
     */
    public function client(?string $checksum = null, int $status = 200, ?string $checksums = null): MockHttpClient
    {
        $checksums ??= \sprintf("%s  d2-v9.9.9-macos-arm64.tar.gz\n%s  %s\n", str_repeat('0', 64), $checksum ?? hash('sha256', $this->archive), self::ASSET);

        return new MockHttpClient(function (string $method, string $url) use ($checksums, $status): MockResponse {
            $this->requestedUrls[] = $url;

            return str_ends_with($url, '/SHA256SUMS')
                ? new MockResponse($checksums)
                : new MockResponse($this->archive, ['http_code' => $status]);
        });
    }
}
