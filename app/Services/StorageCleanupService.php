<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Payment;
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
        $validPaths = $this->referencedStoragePaths();
        $disk = Storage::disk(self::DISK);
        $deleted = 0;

        foreach (['documents', 'receipts', 'id_cards', 'payments'] as $root) {
            if (! $disk->exists($root)) {
                continue;
            }

            $absoluteRoot = $disk->path($root);

            if (! is_dir($absoluteRoot)) {
                continue;
            }

            $finder = Finder::create()
                ->files()
                ->in($absoluteRoot);

            foreach ($finder as $file) {
                $relativePath = $this->toRelativeStoragePath($file->getRealPath());

                if ($relativePath === null || isset($validPaths[$relativePath])) {
                    continue;
                }

                if (@unlink($file->getRealPath())) {
                    $deleted++;
                }
            }

            $this->pruneEmptyDirectories($absoluteRoot);
        }

        return $deleted;
    }

    /**
     * @return array<string, true>
     */
    protected function referencedStoragePaths(): array
    {
        $paths = [];

        Document::query()
            ->pluck('file_path')
            ->filter()
            ->each(fn (string $path) => $paths[$this->normalizePath($path)] = true);

        Payment::query()
            ->get(['proof_image_path', 'receipt_path'])
            ->each(function (Payment $payment) use (&$paths): void {
                if (filled($payment->proof_image_path)) {
                    $paths[$this->normalizePath($payment->proof_image_path)] = true;
                }

                if (filled($payment->receipt_path)) {
                    $paths[$this->normalizePath($payment->receipt_path)] = true;
                }
            });

        Enrollment::query()
            ->whereNotNull('id_card_path')
            ->pluck('id_card_path')
            ->each(fn (string $path) => $paths[$this->normalizePath($path)] = true);

        return $paths;
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

    protected function toRelativeStoragePath(string $absolutePath): ?string
    {
        $root = rtrim(Storage::disk(self::DISK)->path(''), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($absolutePath, $root)) {
            return null;
        }

        return $this->normalizePath(substr($absolutePath, strlen($root)));
    }

    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
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
