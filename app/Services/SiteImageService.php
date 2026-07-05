<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SiteImageService
{
    public const DISK = 'public';

    public const MAX_KILOBYTES = 350;

    public const MAX_BYTES = 358400;

    /** @var list<string> */
    public const STORAGE_PREFIXES = [
        'site/gallery/',
        'site/logo/',
        'site/hero/',
        'site/favicon/',
        'site/',
    ];

    public static function url(?string $path): ?string
    {
        $path = self::resolveExistingPath($path);

        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    public static function delete(?string $path): void
    {
        $path = self::normalizeStoragePath($path);

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

        return filled($state) ? self::normalizeStoragePath((string) $state) : null;
    }

    public static function normalizeStoragePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }

    /**
     * Return the on-disk path when the file exists, trying common upload folders.
     */
    public static function resolveExistingPath(?string $path): ?string
    {
        $path = self::normalizeStoragePath($path);

        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = Storage::disk(self::DISK);

        if ($disk->exists($path)) {
            return $path;
        }

        foreach (self::pathExtensionVariants($path) as $candidate) {
            if ($disk->exists($candidate)) {
                return $candidate;
            }
        }

        $basename = basename($path);

        if ($basename === $path) {
            foreach (self::STORAGE_PREFIXES as $prefix) {
                $candidate = $prefix.$basename;

                if ($disk->exists($candidate)) {
                    return $candidate;
                }

                foreach (self::pathExtensionVariants($candidate) as $variant) {
                    if ($disk->exists($variant)) {
                        return $variant;
                    }
                }
            }
        }

        return $path;
    }

    /**
     * Ensure an uploaded image is stored under the expected directory on the public disk.
     */
    public static function finalizeUploadPath(?string $path, string $directory): ?string
    {
        $path = self::normalizeStoragePath($path);

        if (blank($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = Storage::disk(self::DISK);
        $directory = trim($directory, '/');
        $resolved = self::resolveExistingPath($path);

        if ($resolved && $disk->exists($resolved) && str_starts_with($resolved, $directory.'/')) {
            return $resolved;
        }

        $basename = basename($path);
        $destination = $directory.'/'.$basename;

        $sources = array_unique(array_filter([
            $path,
            $resolved,
            'livewire-tmp/'.$basename,
            ...self::pathExtensionVariants('livewire-tmp/'.$basename),
            ...self::pathExtensionVariants($path),
        ]));

        foreach ($sources as $source) {
            if (! $disk->exists($source)) {
                continue;
            }

            if ($source === $destination) {
                return $destination;
            }

            if (! $disk->exists($destination)) {
                $disk->copy($source, $destination);
            }

            return $destination;
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    public static function pathExtensionVariants(string $path): array
    {
        $path = self::normalizeStoragePath($path);

        if (blank($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return [];
        }

        $info = pathinfo($path);
        $directory = isset($info['dirname']) && $info['dirname'] !== '.' ? $info['dirname'].'/' : '';
        $filename = $info['filename'] ?? '';
        $extension = strtolower($info['extension'] ?? '');

        if ($filename === '') {
            return [];
        }

        $variants = [];

        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            if ($ext !== $extension) {
                $variants[] = $directory.$filename.'.'.$ext;
            }
        }

        return $variants;
    }

    public static function existsOnDisk(?string $path): bool
    {
        $path = self::resolveExistingPath($path);

        if (blank($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return filled($path);
        }

        return Storage::disk(self::DISK)->exists($path);
    }

    public static function ensurePublicStorageLink(): bool
    {
        $link = public_path('storage');

        if (file_exists($link)) {
            return true;
        }

        Artisan::call('storage:link');

        return file_exists($link);
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
