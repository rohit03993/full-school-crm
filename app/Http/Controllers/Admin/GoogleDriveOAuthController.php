<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Filament\Pages\BackupsPage;
use App\Services\AuditService;
use App\Services\GoogleDriveBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class GoogleDriveOAuthController
{
    public function redirect(Request $request, GoogleDriveBackupService $drive): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(RoleName::SuperAdmin->value), 403);

        try {
            return redirect()->away($drive->authorizationUrl());
        } catch (Throwable $exception) {
            return redirect()
                ->to(BackupsPage::getUrl())
                ->with('gdrive_oauth_error', $exception->getMessage());
        }
    }

    public function callback(Request $request, GoogleDriveBackupService $drive, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(RoleName::SuperAdmin->value), 403);

        if ($request->filled('error')) {
            return redirect()
                ->to(BackupsPage::getUrl())
                ->with('gdrive_oauth_error', (string) $request->string('error'));
        }

        $code = (string) $request->string('code');
        $state = $request->string('state')->toString();

        if ($code === '') {
            return redirect()
                ->to(BackupsPage::getUrl())
                ->with('gdrive_oauth_error', 'Google did not return an authorization code.');
        }

        try {
            $email = $drive->handleOAuthCallback($code, $state);
        } catch (Throwable $exception) {
            return redirect()
                ->to(BackupsPage::getUrl())
                ->with('gdrive_oauth_error', $exception->getMessage());
        }

        $audit->log(
            action: 'Google Drive OAuth Connected',
            newValues: ['email' => $email],
            user: $request->user(),
        );

        return redirect()
            ->to(BackupsPage::getUrl())
            ->with('gdrive_oauth_success', 'Signed in as '.$email.'. Save folder ID and Test connection.');
    }
}
