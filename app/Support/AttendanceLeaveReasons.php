<?php

namespace App\Support;

/**
 * Preset leave reason tags for manual attendance (staff can also type a custom reason).
 */
final class AttendanceLeaveReasons
{
    /**
     * @return list<string>
     */
    public static function tags(): array
    {
        return [
            'Sick',
            'Medical appointment',
            'Family function',
            'Travel',
            'Festival / religious',
            'Personal work',
            'Weather / transport',
        ];
    }

    /**
     * Build stored reason from optional tag + optional custom text.
     */
    public static function compose(?string $tag, ?string $custom): string
    {
        $tag = trim((string) $tag);
        $custom = trim((string) $custom);

        if ($tag !== '' && $custom !== '') {
            if (strcasecmp($tag, $custom) === 0) {
                return mb_substr($tag, 0, 255);
            }

            return mb_substr($tag.' — '.$custom, 0, 255);
        }

        if ($custom !== '') {
            return mb_substr($custom, 0, 255);
        }

        if ($tag !== '') {
            return mb_substr($tag, 0, 255);
        }

        return '';
    }

    public static function isValidTag(string $tag): bool
    {
        $tag = trim($tag);

        return $tag !== '' && in_array($tag, self::tags(), true);
    }
}
