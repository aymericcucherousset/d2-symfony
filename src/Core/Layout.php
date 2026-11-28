<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

/**
 * Layout engines supported by d2 (`--layout`).
 */
enum Layout: string
{
    /** The d2 default. */
    case Dagre = 'dagre';

    case Elk = 'elk';

    /** Requires the proprietary TALA plugin next to the d2 binary. */
    case Tala = 'tala';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
