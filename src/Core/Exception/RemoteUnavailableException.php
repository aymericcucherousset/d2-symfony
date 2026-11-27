<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

/**
 * Thrown when a remote rendering server (e.g. Kroki) cannot be reached or fails on its side.
 */
final class RemoteUnavailableException extends \RuntimeException implements RendererUnavailableException
{
}
