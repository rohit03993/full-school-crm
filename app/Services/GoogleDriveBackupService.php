<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GoogleDriveBackupService
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    /**
     * @return array{
     *     enabled: bool,
     *     folder_id: string,
     *     client_email: ?string,
     *     has_credentials: bool,
     *     last_upload_at: ?string,
     *     last_upload_filename: ?string,
     *     last_upload_error: ?string,
     *     last_upload_file_id: ?string,
     * }
     */
    public function status(): array
    {
        $json = $this->serviceAccountPayload();

        return [
            'enabled' => (bool) Setting::getValue('backup.gdrive.enabled', false),
            'folder_id' => (string) Setting::getValue('backup.gdrive.folder_id', ''),
            'client_email' => is_array($json) ? ($json['client_email'] ?? null) : null,
            'has_credentials' => is_array($json) && filled($json['private_key'] ?? null) && filled($json['client_email'] ?? null),
            'last_upload_at' => Setting::getValue('backup.gdrive.last_upload_at'),
            'last_upload_filename' => Setting::getValue('backup.gdrive.last_upload_filename'),
            'last_upload_error' => Setting::getValue('backup.gdrive.last_upload_error'),
            'last_upload_file_id' => Setting::getValue('backup.gdrive.last_upload_file_id'),
        ];
    }

    public function isReady(): bool
    {
        $status = $this->status();

        return $status['enabled']
            && $status['has_credentials']
            && filled($status['folder_id']);
    }

    /**
     * @param  array{enabled?: bool, folder_id?: string, service_account_json?: ?string}  $data
     */
    public function saveSettings(array $data): void
    {
        Setting::setValue('backup.gdrive.enabled', ! empty($data['enabled']) ? '1' : '0', 'backup');
        Setting::setValue('backup.gdrive.folder_id', trim((string) ($data['folder_id'] ?? '')), 'backup');

        $rawJson = trim((string) ($data['service_account_json'] ?? ''));

        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);

            if (! is_array($decoded) || blank($decoded['client_email'] ?? null) || blank($decoded['private_key'] ?? null)) {
                throw new RuntimeException('Invalid service account JSON. It must include client_email and private_key.');
            }

            Setting::setValue(
                'backup.gdrive.service_account_json',
                Crypt::encryptString($rawJson),
                'backup',
            );
        }

        Setting::flushValueCache();
    }

    public function clearCredentials(): void
    {
        Setting::setValue('backup.gdrive.service_account_json', '', 'backup');
        Setting::setValue('backup.gdrive.enabled', '0', 'backup');
        Setting::flushValueCache();
    }

    /**
     * Upload a local backup zip to the configured Drive folder.
     *
     * @return array{file_id: string, filename: string, web_view_link: ?string}
     */
    public function uploadBackup(string $localPath, ?string $filename = null): array
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('Backup file not found for Google Drive upload.');
        }

        if (! $this->isReady()) {
            throw new RuntimeException('Google Drive backup is not connected. Enable it and save folder ID + service account JSON.');
        }

        $filename ??= basename($localPath);
        $folderId = (string) Setting::getValue('backup.gdrive.folder_id', '');
        $accessToken = $this->accessToken();

        $metadata = json_encode([
            'name' => $filename,
            'parents' => [$folderId],
        ], JSON_THROW_ON_ERROR);

        $boundary = 'crm_backup_'.bin2hex(random_bytes(8));
        $fileContents = file_get_contents($localPath);

        if ($fileContents === false) {
            throw new RuntimeException('Could not read backup file for upload.');
        }

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadata."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: application/zip\r\n\r\n"
            .$fileContents."\r\n"
            ."--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->timeout(600)
            ->withBody($body, 'multipart/related; boundary='.$boundary)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink');

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            $this->rememberFailure($filename, (string) $message);

            throw new RuntimeException('Google Drive upload failed: '.$message);
        }

        $fileId = (string) $response->json('id');
        $webViewLink = $response->json('webViewLink');

        Setting::setValue('backup.gdrive.last_upload_at', now()->toIso8601String(), 'backup');
        Setting::setValue('backup.gdrive.last_upload_filename', $filename, 'backup');
        Setting::setValue('backup.gdrive.last_upload_file_id', $fileId, 'backup');
        Setting::setValue('backup.gdrive.last_upload_error', '', 'backup');
        Setting::flushValueCache();

        $this->pruneOldDriveBackups($accessToken, $folderId);

        return [
            'file_id' => $fileId,
            'filename' => $filename,
            'web_view_link' => is_string($webViewLink) ? $webViewLink : null,
        ];
    }

    /**
     * Verify credentials can list the target folder.
     */
    public function testConnection(): string
    {
        if (! $this->status()['has_credentials']) {
            throw new RuntimeException('Paste the Google service account JSON first.');
        }

        $folderId = trim((string) Setting::getValue('backup.gdrive.folder_id', ''));

        if ($folderId === '') {
            throw new RuntimeException('Enter the Google Drive folder ID.');
        }

        $accessToken = $this->accessToken();

        $response = $this->driveHttp($accessToken)
            ->get('https://www.googleapis.com/drive/v3/files/'.$folderId, [
                'fields' => 'id,name,mimeType',
                'supportsAllDrives' => 'true',
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException(
                'Cannot access that folder. Share the Drive folder with the service account email as Editor. Details: '.$message
            );
        }

        $name = (string) ($response->json('name') ?? 'Folder');

        return 'Connected to Drive folder: '.$name;
    }

    protected function accessToken(): string
    {
        $testToken = config('crm-backup.gdrive_test_access_token');

        if (is_string($testToken) && $testToken !== '') {
            return $testToken;
        }

        $payload = $this->serviceAccountPayload();

        if (! is_array($payload)) {
            throw new RuntimeException('Google Drive service account is not configured.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $payload['client_email'],
            'scope' => self::SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$claims;
        $privateKey = openssl_pkey_get_private((string) $payload['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Invalid private_key in service account JSON.');
        }

        $signature = '';

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign Google Drive JWT.');
        }

        $jwt = $unsigned.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            $message = $response->json('error_description') ?? $response->json('error') ?? $response->body();

            throw new RuntimeException('Google Drive auth failed: '.$message);
        }

        return (string) $response->json('access_token');
    }

    protected function pruneOldDriveBackups(string $accessToken, string $folderId): void
    {
        $retain = max(1, (int) config('crm-backup.retain', 14));

        $response = $this->driveHttp($accessToken)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => sprintf(
                "'%s' in parents and name contains 'school-crm-full-backup-' and trashed = false",
                str_replace("'", "\\'", $folderId),
            ),
            'orderBy' => 'createdTime desc',
            'pageSize' => 100,
            'fields' => 'files(id,name,createdTime)',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ]);

        if (! $response->successful()) {
            return;
        }

        $files = $response->json('files') ?? [];

        if (! is_array($files)) {
            return;
        }

        foreach (array_slice($files, $retain) as $old) {
            $id = $old['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            try {
                $this->driveHttp($accessToken)->delete('https://www.googleapis.com/drive/v3/files/'.$id);
            } catch (Throwable) {
                // best-effort prune
            }
        }
    }

    protected function driveHttp(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)->timeout(120)->acceptJson();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function serviceAccountPayload(): ?array
    {
        $stored = Setting::getValue('backup.gdrive.service_account_json');

        if (! is_string($stored) || trim($stored) === '') {
            return null;
        }

        try {
            $json = Crypt::decryptString($stored);
        } catch (Throwable) {
            // Allow plain JSON during migration/tests
            $json = $stored;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function rememberFailure(string $filename, string $message): void
    {
        Setting::setValue('backup.gdrive.last_upload_filename', $filename, 'backup');
        Setting::setValue('backup.gdrive.last_upload_error', mb_substr($message, 0, 500), 'backup');
        Setting::flushValueCache();
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
