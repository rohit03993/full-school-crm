<?php

namespace App\Support;

class ViteManifest
{
    public static function manifestPath(): string
    {
        return public_path('build/manifest.json');
    }

    public static function hasManifest(): bool
    {
        return is_file(self::manifestPath());
    }

    public static function hasEntry(string $entry): bool
    {
        if (! self::hasManifest()) {
            return false;
        }

        $manifest = json_decode((string) file_get_contents(self::manifestPath()), true);

        return is_array($manifest) && array_key_exists($entry, $manifest);
    }

    /**
     * @param  list<string>  $entries
     */
    public static function hasEntries(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (! self::hasEntry($entry)) {
                return false;
            }
        }

        return true;
    }
}
