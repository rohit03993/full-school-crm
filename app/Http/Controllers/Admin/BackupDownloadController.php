<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Services\CrmBackupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupDownloadController
{
    public function __invoke(Request $request, string $filename, CrmBackupService $backups): BinaryFileResponse|StreamedResponse
    {
        abort_unless(
            $request->user()?->hasRole(RoleName::SuperAdmin->value),
            403,
        );

        $path = $backups->findBackup($filename);

        abort_unless($path, 404);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
        ]);
    }
}
