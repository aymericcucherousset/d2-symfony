<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Downloader\Exception;

use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;

/**
 * Thrown when d2 does not publish binaries for the current OS or CPU architecture.
 */
final class UnsupportedPlatformException extends \RuntimeException implements D2Exception
{
}
