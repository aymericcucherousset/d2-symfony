<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Downloader;

use Aymericcucherousset\D2Symfony\Downloader\Exception\UnsupportedPlatformException;

/**
 * Operating system and CPU architecture, as named in the terrastruct/d2 release assets.
 */
final readonly class Platform
{
    private const OPERATING_SYSTEMS = ['Linux' => 'linux', 'Darwin' => 'macos', 'Windows' => 'windows'];
    private const ARCHITECTURES = [
        'x86_64' => 'amd64', 'amd64' => 'amd64', 'x64' => 'amd64',
        'aarch64' => 'arm64', 'arm64' => 'arm64', 'armv8' => 'arm64',
    ];

    /**
     * @param 'linux'|'macos'|'windows' $os
     * @param 'amd64'|'arm64'           $arch
     */
    public function __construct(
        public string $os,
        public string $arch,
    ) {
    }

    /**
     * Detects the platform from PHP_OS_FAMILY and `uname -m`.
     *
     * @throws UnsupportedPlatformException
     */
    public static function detect(string $osFamily = \PHP_OS_FAMILY, ?string $machine = null): self
    {
        $machine ??= php_uname('m');

        $os = self::OPERATING_SYSTEMS[$osFamily] ?? null;
        $arch = self::ARCHITECTURES[strtolower($machine)] ?? null;

        if (null === $os || null === $arch) {
            throw new UnsupportedPlatformException(\sprintf('d2 does not publish binaries for this platform (%s %s); supported platforms are Linux, macOS and Windows on amd64 or arm64. Install d2 manually (https://d2lang.com/tour/install) and set "d2.binary_path".', $osFamily, $machine));
        }

        return new self($os, $arch);
    }

    /**
     * Returns the name of the release asset, e.g. "d2-v0.9.0-linux-amd64.tar.gz".
     */
    public function assetName(string $version): string
    {
        return \sprintf('d2-%s-%s-%s.tar.gz', $version, $this->os, $this->arch);
    }

    /**
     * Returns the path of the binary inside the release archive, e.g. "d2-v0.9.0/bin/d2".
     */
    public function archiveEntry(string $version): string
    {
        return \sprintf('d2-%s/bin/%s', $version, $this->binaryName());
    }

    public function binaryName(): string
    {
        return 'windows' === $this->os ? 'd2.exe' : 'd2';
    }

    public function __toString(): string
    {
        return $this->os.'-'.$this->arch;
    }
}
