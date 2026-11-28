<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

/**
 * Output formats a diagram can be rendered to (`--stdout-format`).
 *
 * PNG and PDF are rendered by d2 itself since v0.9.0; older d2 versions download
 * Playwright and Chromium on the first export.
 */
enum Format: string
{
    /** The d2 default. */
    case Svg = 'svg';

    case Png = 'png';

    case Pdf = 'pdf';

    /**
     * Returns the media type of the rendered document, e.g. for a Content-Type header or a data URI.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::Svg => 'image/svg+xml',
            self::Png => 'image/png',
            self::Pdf => 'application/pdf',
        };
    }

    /**
     * Returns the format matching a file extension ("png", ".PNG"…), or null when none matches.
     */
    public static function fromExtension(string $extension): ?self
    {
        return self::tryFrom(strtolower(ltrim($extension, '.')));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
