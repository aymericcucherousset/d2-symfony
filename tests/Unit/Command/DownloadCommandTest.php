<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Command;

use Aymericcucherousset\D2Symfony\Command\DownloadCommand;
use Aymericcucherousset\D2Symfony\Downloader\BinaryDownloader;
use Aymericcucherousset\D2Symfony\Downloader\Platform;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FakeD2Release;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;

final class DownloadCommandTest extends TestCase
{
    private string $dir;
    private FakeD2Release $release;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/d2-command-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->release = new FakeD2Release($this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testItDownloadsTheBinary(): void
    {
        $tester = $this->tester($this->release->client());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Downloading d2 v9.9.9 (linux-amd64)', $tester->getDisplay());
        self::assertStringContainsString('installed at', $tester->getDisplay());
        self::assertFileExists($this->dir.'/v9.9.9/d2');
    }

    public function testAlreadyInstalledBinaryIsKept(): void
    {
        $this->tester($this->release->client())->execute([]);

        $tester = $this->tester(new MockHttpClient([]));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('already installed', $tester->getDisplay());
    }

    public function testForceDownloadsAgain(): void
    {
        $this->tester($this->release->client())->execute([]);
        $this->release = new FakeD2Release($this->dir);

        $tester = $this->tester($this->release->client());

        self::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));
        self::assertCount(2, $this->release->requestedUrls);
    }

    public function testFailureIsReported(): void
    {
        $tester = $this->tester($this->release->client(checksum: str_repeat('a', 64)));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Checksum mismatch', $tester->getDisplay());
        self::assertFileDoesNotExist($this->dir.'/v9.9.9/d2');
    }

    private function tester(MockHttpClient $client): CommandTester
    {
        $downloader = new BinaryDownloader($this->dir, FakeD2Release::VERSION, $client, new Platform('linux', 'amd64'), 'https://example.com');

        return new CommandTester(new DownloadCommand($downloader));
    }
}
