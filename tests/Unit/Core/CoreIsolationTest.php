<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Guards src/Core against framework coupling until Deptrac takes over.
 */
final class CoreIsolationTest extends TestCase
{
    public function testCoreDoesNotDependOnSymfonyExceptProcessAndHttpClientContracts(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(\dirname(__DIR__, 3).'/src/Core', \FilesystemIterator::SKIP_DOTS),
        );

        $violations = [];
        $checked = 0;

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }

            ++$checked;
            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all('/^use\s+\\\\?(Symfony\\\\[^;\s]+)/m', $contents, $matches);

            foreach ($matches[1] as $import) {
                if (!str_starts_with($import, 'Symfony\\Component\\Process\\') && !str_starts_with($import, 'Symfony\\Contracts\\HttpClient\\')) {
                    $violations[] = \sprintf('%s imports %s', $file->getFilename(), $import);
                }
            }
        }

        self::assertGreaterThan(0, $checked);
        self::assertSame([], $violations);
    }
}
