<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GoogleDriveBackupService
{
    /**
     * User OAuth scope — uploads use the signed-in Google account's storage quota.
     */
    public const OAUTH_SCOPE = 'https://www.googleapis.com/auth/drive';

    /**
     * @return array{
     *     enabled: bool,
     *     folder_id: string,
     *     auth_mode: string,
     *     oauth_connected: bool,
     *     oauth_email: ?string,
     *     has_oauth_client: bool,
     *     client_email: ?string,
     *     has_credentials: bool,
     *     last_upload_at: ?string,
     *     last_upload_filename: ?string,
     *     last_upload_error: ?string,
     *     last_upload_file_id: ?string,
     *     last_test_ok: bool,
     *     last_test_at: ?string,
     *     last_test_folder_name: ?string,
     *     redirect_uri: string,
     * }
     */
    public function status(): array
    {
        $json = $this->serviceAccountPayload();
        $hasOauth = filled($this->decryptSetting('backup.gdrive.oauth_refresh_token'));

        return [
            'enabled' => (bool) Setting::getValue('backup.gdrive.enabled', false),
            'folder_id' => (string) Setting::getValue('backup.gdrive.folder_id', ''),
            'auth_mode' => $hasOauth ? 'oauth' : (is_array($json) ? 'service_account' : 'none'),
            'oauth_connected' => $hasOauth,
            'oauth_email' => Setting::getValue('backup.gdrive.oauth_email'),
            'has_oauth_client' => filled($this->oauthClientId()) && filled($this->oauthClientSecret()),
            'client_email' => is_array($json) ? ($json['client_email'] ?? null) : null,
            'has_credentials' => $hasOauth || (is_array($json) && filled($json['private_key'] ?? null) && filled($json['client_email'] ?? null)),
            'last_upload_at' => Setting::getValue('backup.gdrive.last_upload_at'),
            'last_upload_filename' => Setting::getValue('backup.gdrive.last_upload_filename'),
            'last_upload_error' => Setting::getValue('backup.gdrive.last_upload_error'),
            'last_upload_file_id' => Setting::getValue('backup.gdrive.last_upload_file_id'),
            'last_test_ok' => (bool) Setting::getValue('backup.gdrive.last_test_ok', false),
            'last_test_at' => Setting::getValue('backup.gdrive.last_test_at'),
            'last_test_folder_name' => Setting::getValue('backup.gdrive.last_test_folder_name'),
            'redirect_uri' => $this->redirectUri(),
        ];
    }

    public function isReady(): bool
    {
        $status = $this->status();

        return $status['enabled']
            && $status['has_credentials']
            && filled($status['folder_id']);
    }

    public function redirectUri(): string
    {
        return url('/admin/backups/google/callback');
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     folder_id?: string,
     *     oauth_client_id?: ?string,
     *     oauth_client_secret?: ?string,
     *     service_account_json?: ?string
     * }  $data
     */
    public function saveSettings(array $data): void
    {
        Setting::setValue('backup.gdrive.enabled', ! empty($data['enabled']) ? '1' : '0', 'backup');
        Setting::setValue('backup.gdrive.folder_id', trim((string) ($data['folder_id'] ?? '')), 'backup');

        $clientId = trim((string) ($data['oauth_client_id'] ?? ''));
        if ($clientId !== '') {
            Setting::setValue('backup.gdrive.oauth_client_id', $clientId, 'backup');
        }

        $clientSecret = trim((string) ($data['oauth_client_secret'] ?? ''));
        if ($clientSecret !== '') {
            Setting::setValue('backup.gdrive.oauth_client_secret', Crypt::encryptString($clientSecret), 'backup');
        }

        $rawJson = trim((string) ($data['service_account_json'] ?? ''));
        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);

            if (! is_array($decoded) || blank($decoded['client_email'] ?? null) || blank($decoded['private_key'] ?? null)) {
                throw new RuntimeException('Invalid service account JSON. Prefer Sign in with Google for personal Drive.');
            }

            Setting::setValue(
                'backup.gdrive.service_account_json',
                Crypt::encryptString($rawJson),
                'backup',
            );
        }

        Setting::flushValueCache();
    }

    public function authorizationUrl(): string
    {
        $clientId = $this->oauthClientId();
        $clientSecret = $this->oauthClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Save Google OAuth Client ID and Client Secret first.');
        }

        $state = Str::random(40);
        session(['gdrive_oauth_state' => $state]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::OAUTH_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function handleOAuthCallback(string $code, ?string $state): string
    {
        $expected = session('gdrive_oauth_state');
        session()->forget('gdrive_oauth_state');

        if (! is_string($expected) || $expected === '' || $state !== $expected) {
            throw new RuntimeException('Invalid OAuth state. Try Connect Google Drive again.');
        }

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $this->oauthClientId(),
                'client_secret' => $this->oauthClientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful() || (blank($response->json('refresh_token')) && blank($response->json('access_token')))) {
            $message = $response->json('error_description') ?? $response->json('error') ?? $response->body();

            throw new RuntimeException('Google sign-in failed: '.$message);
        }

        $refresh = $response->json('refresh_token');
        if (is_string($refresh) && $refresh !== '') {
            Setting::setValue('backup.gdrive.oauth_refresh_token', Crypt::encryptString($refresh), 'backup');
        } elseif (! filled($this->decryptSetting('backup.gdrive.oauth_refresh_token'))) {
            throw new RuntimeException(
                'Google did not return a refresh token. Revoke app access at https://myaccount.google.com/permissions then Connect again.'
            );
        }

        $accessToken = (string) $response->json('access_token');
        $email = $this->fetchUserEmail($accessToken);
        if ($email !== null) {
            Setting::setValue('backup.gdrive.oauth_email', $email, 'backup');
        }

        Setting::setValue('backup.gdrive.enabled', '1', 'backup');
        Setting::setValue('backup.gdrive.last_test_ok', '0', 'backup');
        Setting::setValue('backup.gdrive.last_upload_error', '', 'backup');
        Setting::flushValueCache();

        return $email ?? 'Google account';
    }

    public function clearCredentials(): void
    {
        Setting::setValue('backup.gdrive.service_account_json', '', 'backup');
        Setting::setValue('backup.gdrive.oauth_refresh_token', '', 'backup');
        Setting::setValue('backup.gdrive.oauth_email', '', 'backup');
        Setting::setValue('backup.gdrive.enabled', '0', 'backup');
        Setting::setValue('backup.gdrive.last_test_ok', '0', 'backup');
        Setting::flushValueCache();
    }

    /**
     * @return array{file_id: string, filename: string, web_view_link: ?string}
     */
    public function uploadBackup(string $localPath, ?string $filename = null): array
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('Backup file not found for Google Drive upload.');
        }

        if (! $this->isReady()) {
            throw new RuntimeException('Google Drive is not connected. Use Sign in with Google and set the folder ID.');
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

    public function testConnection(): string
    {
        if (! $this->status()['has_credentials']) {
            throw new RuntimeException('Connect Google Drive with Sign in with Google first.');
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
            Setting::setValue('backup.gdrive.last_test_ok', '0', 'backup');
            Setting::setValue('backup.gdrive.last_upload_error', mb_substr((string) $message, 0, 500), 'backup');
            Setting::flushValueCache();

            throw new RuntimeException(
                'Cannot access that folder. Use a folder in the same Google account you signed in with. Details: '.$message
            );
        }

        $name = (string) ($response->json('name') ?? 'Folder');

        Setting::setValue('backup.gdrive.last_test_ok', '1', 'backup');
        Setting::setValue('backup.gdrive.last_test_at', now()->toIso8601String(), 'backup');
        Setting::setValue('backup.gdrive.last_test_folder_name', $name, 'backup');
        Setting::setValue('backup.gdrive.last_upload_error', '', 'backup');
        Setting::flushValueCache();

        $who = Setting::getValue('backup.gdrive.oauth_email') ?: 'Google account';

        return 'Connected as '.$who.' → folder: '.$name;
    }

    protected function accessToken(): string
    {
        $testToken = config('crm-backup.gdrive_test_access_token');

        if (is_string($testToken) && $testToken !== '') {
            return $testToken;
        }

        $refresh = $this->decryptSetting('backup.gdrive.oauth_refresh_token');

        if (is_string($refresh) && $refresh !== '') {
            return $this->accessTokenFromRefresh($refresh);
        }

        return $this->accessTokenFromServiceAccount();
    }

    protected function accessTokenFromRefresh(string $refreshToken): string
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->oauthClientId(),
                'client_secret' => $this->oauthClientSecret(),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            $message = $response->json('error_description') ?? $response->json('error') ?? $response->body();

            throw new RuntimeException('Google token refresh failed. Disconnect and Sign in with Google again. '.$message);
        }

        return (string) $response->json('access_token');
    }

    protected function accessTokenFromServiceAccount(): string
    {
        $payload = $this->serviceAccountPayload();

        if (! is_array($payload)) {
            throw new RuntimeException('Google Drive is not connected.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $payload['client_email'],
            'scope' => self::OAUTH_SCOPE,
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

    protected function fetchUserEmail(string $accessToken): ?string
    {
        $response = $this->driveHttp($accessToken)
            ->get('https://www.googleapis.com/drive/v3/about', [
                'fields' => 'user(emailAddress,displayName)',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $email = $response->json('user.emailAddress');

        return is_string($email) && $email !== '' ? $email : null;
    }

    protected function oauthClientId(): string
    {
        $fromSettings = trim((string) Setting::getValue('backup.gdrive.oauth_client_id', ''));

        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim((string) config('crm-backup.google_client_id', env('GOOGLE_DRIVE_CLIENT_ID', '')));
    }

    protected function oauthClientSecret(): string
    {
        $fromSettings = $this->decryptSetting('backup.gdrive.oauth_client_secret');

        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }

        return trim((string) config('crm-backup.google_client_secret', env('GOOGLE_DRIVE_CLIENT_SECRET', '')));
    }

    protected function decryptSetting(string $key): ?string
    {
        $stored = Setting::getValue($key);

        if (! is_string($stored) || trim($stored) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return $stored;
        }
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
