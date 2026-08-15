<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

class StorageCleanupService
{
    public const DISK = 'local';

    /**
     * @return array{livewire_temp: int, orphan_files: int}
     */
    public function run(): array
    {
        return [
            'livewire_temp' => $this->pruneLivewireTempFiles(),
            'orphan_files' => $this->pruneOrphanStoredFiles(),
        ];
    }

    public function deleteStoredFile(?string $relativePath): void
    {
        if (blank($relativePath)) {
            return;
        }

        $disk = Storage::disk(self::DISK);

        if ($disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }
    }

    public function replaceStoredFile(?string $previousPath, string $newPath): void
    {
        if (filled($previousPath) && $previousPath !== $newPath) {
            $this->deleteStoredFile($previousPath);
        }

        $this->deleteStoredFile($newPath);
    }

    public function pruneLivewireTempFiles(?int $maxAgeHours = null): int
    {
        $maxAgeHours ??= (int) config('institute.storage.livewire_temp_max_age_hours', 24);
        $cutoffTimestamp = now()->subHours($maxAgeHours)->getTimestamp();
        $deleted = 0;

        foreach ($this->livewireTempDirectories() as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $finder = Finder::create()
                ->files()
                ->in($directory);

            foreach ($finder as $file) {
                if ($file->getMTime() > $cutoffTimestamp) {
                    continue;
                }

                if (@unlink($file->getRealPath())) {
                    $deleted++;
                }
            }

            $this->pruneEmptyDirectories($directory);
        }

        return $deleted;
    }

    public function pruneOrphanStoredFiles(): int
    {
        // Never auto-delete under documents/, receipts/, id_cards/, or payments/.
        // A path-mismatch bug here would wipe files while DB rows remain (broken previews).
        // Livewire temp cleanup above still runs; only orphan sweeps of critical folders are skipped.
        return 0;
    }

    /**
     * @return list<string>
     */
    protected function livewireTempDirectories(): array
    {
        return array_unique([
            storage_path('app/livewire-tmp'),
            storage_path('app/private/livewire-tmp'),
            storage_path('framework/livewire-tmp'),
        ]);
    }

    protected function pruneEmptyDirectories(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }

        $directories = array_reverse(iterator_to_array(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            ),
        ));

        foreach ($directories as $directory) {
            if (! $directory->isDir()) {
                continue;
            }

            $path = $directory->getRealPath();

            if ($path === false || $path === $root) {
                continue;
            }

            @rmdir($path);
        }
    }
}
