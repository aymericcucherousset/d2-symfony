<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

/**
 * Thrown when a diagram or its options are built with an invalid value.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements D2Exception
{
}
