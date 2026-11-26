<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Downloader\Exception;

use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;

/**
 * Thrown when the d2 binary cannot be downloaded, verified or installed.
 */
class DownloadException extends \RuntimeException implements D2Exception
{
}
