<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BackupRestoreChunkController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole(RoleName::SuperAdmin->value)) {
            return response()->json([
                'message' => 'Only Super Admin can restore a backup.',
            ], 403);
        }

        $validated = $request->validate([
            'upload_id' => ['required', 'regex:/^[a-f0-9]{32}$/'],
            'index' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1', 'max:20000'],
            'original_name' => ['required', 'string', 'max:180'],
            'chunk' => ['required', 'file', 'max:8192'],
        ]);

        $index = (int) $validated['index'];
        $total = (int) $validated['total'];

        if ($index >= $total) {
            return response()->json([
                'message' => 'Upload piece is out of range.',
            ], 422);
        }

        $originalName = basename((string) $validated['original_name']);

        if (! str_ends_with(strtolower($originalName), '.zip')) {
            return response()->json([
                'message' => 'Choose a .zip backup file.',
            ], 422);
        }

        $directory = storage_path('app/private/.restore-upload');
        File::ensureDirectoryExists($directory);

        $uploadId = (string) $validated['upload_id'];
        $partPath = $directory.DIRECTORY_SEPARATOR.$uploadId.'.part';
        $metaPath = $directory.DIRECTORY_SEPARATOR.$uploadId.'.json';
        $expected = 0;

        if (is_file($metaPath)) {
            $meta = json_decode((string) File::get($metaPath), true);
            $expected = (int) (is_array($meta) ? ($meta['received'] ?? 0) : 0);
        }

        if ($index === $expected - 1 && $expected > 0) {
            return response()->json([
                'done' => $expected === $total,
                'received' => $expected,
                'stored_name' => $expected === $total
                    ? 'school-crm-full-backup-upload-'.$uploadId.'.zip'
                    : null,
            ]);
        }

        if ($index !== $expected) {
            return response()->json([
                'message' => 'Upload got out of order. Choose the file and start again.',
            ], 409);
        }

        $chunk = $request->file('chunk');
        $source = fopen($chunk->getRealPath(), 'rb');
        $target = fopen($partPath, $index === 0 ? 'wb' : 'ab');

        if ($source === false || $target === false) {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($target)) {
                fclose($target);
            }

            return response()->json([
                'message' => 'Could not save this piece of the backup.',
            ], 500);
        }

        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);

        if (is_file($partPath) && filesize($partPath) > 4 * 1024 * 1024 * 1024) {
            @unlink($partPath);
            @unlink($metaPath);

            return response()->json([
                'message' => 'This backup zip is larger than 4 GB.',
            ], 422);
        }

        $received = $index + 1;

        File::put($metaPath, json_encode([
            'original_name' => $originalName,
            'total' => $total,
            'received' => $received,
        ], JSON_UNESCAPED_SLASHES));

        if ($received < $total) {
            return response()->json([
                'done' => false,
                'received' => $received,
            ]);
        }

        $storedName = 'school-crm-full-backup-upload-'.$uploadId.'.zip';
        $finalPath = $directory.DIRECTORY_SEPARATOR.$storedName;

        if (is_file($finalPath)) {
            @unlink($finalPath);
        }

        if (! rename($partPath, $finalPath)) {
            return response()->json([
                'message' => 'Could not finish saving the backup zip.',
            ], 500);
        }

        @unlink($metaPath);

        return response()->json([
            'done' => true,
            'received' => $received,
            'stored_name' => $storedName,
        ]);
    }
}
