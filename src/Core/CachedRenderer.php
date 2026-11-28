<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Caches the documents (SVG, PNG, PDF) produced by another renderer in a PSR-6 pool.
 *
 * The cache key is built from the diagram fingerprint (source and options, format included) and the
 * version of the rendering engine, so diagrams are rendered again after an upgrade.
 * Failed renders are never cached.
 */
final class CachedRenderer implements VersionAwareRenderer
{
    private const SVG_KEY_PREFIX = 'd2.svg.';
    private const VERSION_KEY_PREFIX = 'd2.version.';

    private ?string $version = null;

    /**
     * @param int $versionTtl how long (in seconds) the engine version is kept in the pool, so it is not
     *                        computed again on every request; an upgrade is picked up after this delay
     *                        or when the pool is cleared
     */
    public function __construct(
        private readonly Renderer $inner,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $versionTtl = 300,
    ) {
        if ($versionTtl <= 0) {
            throw new InvalidArgumentException(\sprintf('The version TTL must be greater than 0, %d given.', $versionTtl));
        }
    }

    public function render(Diagram $diagram): string
    {
        $item = $this->cache->getItem(self::SVG_KEY_PREFIX.hash('sha256', $diagram->fingerprint()."\0".$this->getVersion()));

        $svg = $item->isHit() ? $item->get() : null;
        if (\is_string($svg) && '' !== $svg) {
            return $svg;
        }

        $svg = $this->inner->render($diagram);
        $this->cache->save($item->set($svg));

        return $svg;
    }

    /**
     * Returns the version of the decorated renderer, or an empty string when it does not expose one.
     */
    public function getVersion(): string
    {
        if (null !== $this->version) {
            return $this->version;
        }

        if (!$this->inner instanceof VersionAwareRenderer) {
            return $this->version = '';
        }

        $item = $this->cache->getItem(self::VERSION_KEY_PREFIX.hash('xxh128', $this->inner::class));
        $version = $item->isHit() ? $item->get() : null;

        if (!\is_string($version)) {
            $version = $this->inner->getVersion();
            $this->cache->save($item->set($version)->expiresAfter($this->versionTtl));
        }

        return $this->version = $version;
    }
}
