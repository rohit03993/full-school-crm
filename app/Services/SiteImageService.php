<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class SiteImageService
{
    public const DISK = 'public';

    public const MAX_KILOBYTES = 350;

    public const MAX_BYTES = 358400;

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $normalized = ltrim((string) $path, '/');

        return $normalized !== '' ? '/storage/'.$normalized : null;
    }

    public static function delete(?string $path): void
    {
        if (blank($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function replace(?string $oldPath, ?string $newPath): void
    {
        $newPath = self::normalizePath($newPath);
        $oldPath = self::normalizePath($oldPath);

        if ($oldPath && $oldPath !== $newPath) {
            self::delete($oldPath);
        }
    }

    public static function normalizePath(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (is_array($state)) {
            $state = array_values($state)[0] ?? null;
        }

        return filled($state) ? (string) $state : null;
    }

    public static function fileUploadDefaults(): array
    {
        return [
            'disk' => self::DISK,
            'visibility' => 'public',
            'maxSize' => self::MAX_KILOBYTES,
            'acceptedFileTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        ];
    }
}
