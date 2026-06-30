<?php

namespace App\Support;

/**
 * Fixed website header logo frame — keep upload crop and public display in sync.
 */
class SiteLogo
{
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
}
