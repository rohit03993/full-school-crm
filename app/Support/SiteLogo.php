<?php

namespace App\Support;

/**
 * Website header logo frame — keep upload crop and public display in sync.
 *
 * Institutes send us two very different logo shapes: wide wordmarks (name set
 * beside the mark) and square/circular crests. Forcing a crest into the wide
 * frame padded it with blank space and shrank the mark until it was unreadable,
 * so the shape is stored alongside the file and every header renders to match.
 */
class SiteLogo
{
    /** Wide wordmark: the logo already contains the institute name. */
    public const SHAPE_WIDE = 'wide';

    /** Square or circular crest: pair it with the institute name in text. */
    public const SHAPE_SQUARE = 'square';

    public const DEFAULT_SHAPE = self::SHAPE_WIDE;

    /** Crop / export aspect ratio (width:height). */
    public const ASPECT_RATIO = '13:4';

    public const ASPECT_WIDTH = 13;

    public const ASPECT_HEIGHT = 4;

    /** Exported image pixels (2× header display for sharp screens). */
    public const EXPORT_WIDTH = 520;

    public const EXPORT_HEIGHT = 160;

    /** Max rendered width in the public header (px). */
    public const DISPLAY_MAX_WIDTH = 260;

    public const DISPLAY_HEIGHT = 80;

    /** Square crest crop / export. */
    public const SQUARE_ASPECT_RATIO = '1:1';

    public const SQUARE_EXPORT_SIZE = 320;

    /**
     * @return array<string, string>
     */
    public static function shapeOptions(): array
    {
        return [
            self::SHAPE_WIDE => 'Wide banner — logo already includes the institute name',
            self::SHAPE_SQUARE => 'Square / circular — crest or emblem shown next to the name',
        ];
    }

    public static function normalizeShape(mixed $value): string
    {
        $shape = is_string($value) ? strtolower(trim($value)) : '';

        return array_key_exists($shape, self::shapeOptions()) ? $shape : self::DEFAULT_SHAPE;
    }

    public static function isSquare(mixed $value): bool
    {
        return self::normalizeShape($value) === self::SHAPE_SQUARE;
    }

    /**
     * Filament brand logo height — a crest needs more room than a wordmark
     * because its height carries the whole mark.
     */
    public static function panelLogoHeight(mixed $shape): string
    {
        return self::isSquare($shape) ? '2.75rem' : '2rem';
    }
}
