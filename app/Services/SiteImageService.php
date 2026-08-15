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

    /**
     * URL with a cache-busting token so browsers pick up a replaced image.
     *
     * Favicons in particular are cached hard by browsers and by the service
     * worker, so a stale tab icon survives long after the institute uploads
     * a new one unless the URL itself changes.
     */
    public static function versionedUrl(?string $path): ?string
    {
        $url = self::url($path);

        if (blank($url)) {
            return null;
        }

        $version = self::version($path);

        if ($version === null) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$version;
    }

    public static function version(?string $path): ?string
    {
        $path = self::resolveExistingPath($path);

        if (blank($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        return substr(md5((string) $disk->lastModified($path)), 0, 8);
    }

    /**
     * Intrinsic aspect ratio (width / height) of a stored image.
     *
     * The header used to assume every logo was a wide banner, which left a
     * square crest floating in a strip of empty space. Reading the real shape
     * means the frame fits whatever the institute actually uploaded.
     *
     * @return float|null null when the file is missing or unreadable
     */
    public static function aspectRatio(?string $path): ?float
    {
        $path = self::resolveExistingPath($path);

        if (blank($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        $size = @getimagesize($disk->path($path));

        if ($size === false || empty($size[0]) || empty($size[1])) {
            return null;
        }

        return round($size[0] / $size[1], 4);
    }

    /**
     * MIME type from the stored extension.
     *
     * The <link rel="icon"> type attribute must match the real file: declaring
     * image/png for a JPEG upload makes browsers skip it and fall back to the
     * default favicon.
     */
    public static function mimeType(?string $path): ?string
    {
        $path = self::resolveExistingPath($path);

        if (blank($path)) {
            return null;
        }

        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg', 'jfif' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            default => null,
        };
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
