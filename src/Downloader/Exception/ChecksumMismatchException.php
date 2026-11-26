<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Downloader\Exception;

/**
 * Thrown when a downloaded archive does not match the published SHA-256 checksum.
 */
final class ChecksumMismatchException extends DownloadException
{
}
