<div class="mx-auto max-w-3xl space-y-6 pb-24 lg:pb-6">
    <div class="rounded-2xl bg-sky-50 px-4 py-4 text-sm text-sky-950 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-100 dark:ring-sky-500/20">
        <p class="font-semibold">Full institute backup (disaster recovery)</p>
        <p class="mt-1 text-sky-900/90 dark:text-sky-200/90">
            Each zip includes the <strong>entire database</strong> plus <strong>all uploaded files</strong>:
            student photos, Aadhaar, documents, fee receipts, ID cards, homework, website logos/gallery,
            WhatsApp media, cases, call logs, attendance, fees — everything needed to restore the CRM as it was.
        </p>
        <p class="mt-2 text-xs text-sky-800 dark:text-sky-300">
            Daily automatic backup runs at {{ $scheduleAt }} (server time). Latest {{ $retain }} archives are kept on the server
            @if ($drive['enabled'] && $drive['has_credentials'])
                and uploaded to <strong>Google Drive</strong>
            @endif
            . Restore only with matching <code class="rounded bg-white/70 px-1 dark:bg-black/30">APP_KEY</code>.
        </p>
    </div>

    {{-- Google Drive --}}
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
            <h2 class="text-sm font-bold text-gray-950 dark:text-white">Google Drive (automatic off-site copy)</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Sign in with your Google account so backups use your Drive storage (not a service account).
            </p>
        </div>

        <div class="space-y-4 px-4 py-4 sm:px-6">
            <div class="rounded-xl px-3 py-2 text-xs ring-1
                {{ $drive['last_test_ok'] ?? false
                    ? 'bg-emerald-50 text-emerald-900 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20'
                    : 'bg-amber-50 text-amber-950 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/20' }}">
                @if ($drive['last_test_ok'] ?? false)
                    Verified
                    @if ($drive['oauth_email'])
                        · {{ $drive['oauth_email'] }}
                    @elseif ($drive['client_email'])
                        · {{ $drive['client_email'] }}
                    @endif
                    · folder “{{ $drive['last_test_folder_name'] ?? 'Drive' }}”
                    @if ($drive['last_upload_at'])
                        · Last upload: {{ \Illuminate\Support\Carbon::parse($drive['last_upload_at'])->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
                        ({{ $drive['last_upload_filename'] }})
                    @endif
                @elseif ($drive['oauth_connected'])
                    Signed in
                    @if ($drive['oauth_email'])
                        as {{ $drive['oauth_email'] }}
                    @endif
                    — save folder ID and click <strong>Test connection</strong>.
                @elseif ($drive['enabled'] && $drive['has_credentials'] && filled($drive['folder_id']))
                    Settings saved — click <strong>Test connection</strong> to verify.
                @else
                    Not connected — create an OAuth client, save Client ID/Secret, then Sign in with Google.
                @endif
                @if (filled($drive['last_upload_error']))
                    <p class="mt-1 font-medium text-rose-700 dark:text-rose-300">Last error: {{ $drive['last_upload_error'] }}</p>
                @endif
            </div>

            <ol class="list-decimal space-y-1.5 pl-4 text-xs text-gray-600 dark:text-gray-400">
                <li>Google Cloud Console → create/select project → enable <strong>Google Drive API</strong>.</li>
                <li>APIs &amp; Services → <strong>OAuth consent screen</strong> (External / Testing) → add your Gmail as a test user.</li>
                <li>Credentials → <strong>Create credentials → OAuth client ID → Web application</strong>.</li>
                <li>
                    Add authorized redirect URI:
                    <code class="break-all rounded bg-gray-100 px-1 dark:bg-white/10">{{ $drive['redirect_uri'] }}</code>
                </li>
                <li>Copy Client ID and Client Secret into the fields below → Save.</li>
                <li>Create a Drive folder (e.g. <strong>School CRM Backups</strong>) → copy the <strong>folder ID</strong> from the URL (<code>…/folders/FOLDER_ID</code>).</li>
                <li>Click <strong>Sign in with Google</strong>, then <strong>Test connection</strong>.</li>
            </ol>

            <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                <input type="checkbox" wire:model="gdriveEnabled" class="rounded border-gray-300">
                Enable automatic upload to Google Drive
            </label>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">OAuth Client ID</label>
                    <input type="text" wire:model="gdriveClientId" class="fi-crm-input mt-1 block w-full font-mono text-xs" placeholder="….apps.googleusercontent.com" autocomplete="off" />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        OAuth Client Secret
                        @if ($drive['has_oauth_client'])
                            <span class="font-normal text-gray-400">(saved — leave blank to keep)</span>
                        @endif
                    </label>
                    <input type="password" wire:model="gdriveClientSecret" class="fi-crm-input mt-1 block w-full font-mono text-xs" placeholder="GOCSPX-…" autocomplete="new-password" />
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Drive folder ID</label>
                <input type="text" wire:model="gdriveFolderId" class="fi-crm-input mt-1 block w-full font-mono text-xs" placeholder="1AbCDefGhi…" />
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="saveGoogleDriveSettings" class="inline-flex rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-500">
                    Save Drive settings
                </button>
                <button
                    type="button"
                    wire:click="connectGoogleDrive"
                    class="inline-flex rounded-lg bg-white px-3 py-2 text-xs font-semibold text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:ring-white/20 dark:hover:bg-white/15"
                >
                    Sign in with Google
                </button>
                <button type="button" wire:click="testGoogleDrive" class="inline-flex rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-500">
                    Test connection
                </button>
                <button type="button" wire:click="uploadLatestToDrive" class="inline-flex rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500">
                    Upload latest backup now
                </button>
                @if ($drive['has_credentials'] || $drive['oauth_connected'])
                    <button
                        type="button"
                        wire:click="disconnectGoogleDrive"
                        wire:confirm="Disconnect Google Drive? Nightly uploads will stop until you reconnect."
                        class="inline-flex rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200"
                    >
                        Disconnect
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Create / list --}}
    <div class="flex flex-wrap items-center gap-3">
        <button
            type="button"
            wire:click="createBackup"
            wire:loading.attr="disabled"
            class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="createBackup">Create full backup now</span>
            <span wire:loading wire:target="createBackup">Creating backup…</span>
        </button>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Also uploads to Drive when connected. Large institutes may take a few minutes.
        </p>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
            <h2 class="text-sm font-bold text-gray-950 dark:text-white">Backups on this server</h2>
        </div>

        @if (count($backups) === 0)
            <div class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                No backups yet. Create one now, or wait for the nightly schedule.
            </div>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($backups as $backup)
                    <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">
                                {{ $backup['filename'] }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $backup['created_at']?->format('d M Y, h:i A') }}
                                · {{ $formatBytes($backup['size_bytes']) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ route('admin.backups.download', ['filename' => $backup['filename']]) }}"
                                class="inline-flex rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500"
                            >
                                Download
                            </a>
                            <button
                                type="button"
                                wire:click="restoreFromServer({{ json_encode($backup['filename']) }})"
                                wire:confirm="Restore this backup? All current students, fees, files, and settings will be replaced."
                                wire:loading.attr="disabled"
                                class="inline-flex rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-600 disabled:opacity-60"
                            >
                                Restore this copy
                            </button>
                            <button
                                type="button"
                                wire:click="deleteBackup({{ json_encode($backup['filename']) }})"
                                wire:confirm="Delete this backup zip from the server?"
                                class="inline-flex rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15"
                            >
                                Delete
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Restore UI --}}
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-rose-200/80 dark:bg-gray-900 dark:ring-rose-500/30">
        <div class="border-b border-rose-100 bg-rose-50/80 px-4 py-3 dark:border-rose-500/20 dark:bg-rose-500/10 sm:px-6">
            <h2 class="text-sm font-bold text-rose-950 dark:text-rose-100">Restore from backup (Admin UI)</h2>
            <p class="mt-1 text-xs text-rose-800/90 dark:text-rose-200/90">
                Use <strong>Restore this copy</strong> when the zip is already in the list above.
                Use the box below when the zip is on your computer or in Google Drive.
            </p>
        </div>

        <div
            class="space-y-4 px-4 py-4 sm:px-6"
            x-data="{
                progress: '',
                busy: false,
                async start() {
                    if (this.busy) {
                        return;
                    }

                    const file = this.$refs.file.files[0];

                    if (! file) {
                        this.progress = 'Choose the backup zip first.';
                        return;
                    }

                    if (! this.$refs.confirm.checked) {
                        this.progress = 'Tick the confirmation box first.';
                        return;
                    }

                    if (! confirm('Restore now? All current students, fees, files, and settings will be replaced.')) {
                        return;
                    }

                    this.busy = true;
                    this.progress = 'Uploading…';

                    try {
                        const storedName = await this.upload(file);
                        this.progress = 'Restoring… keep this tab open';
                        await this.$wire.set('restoreConfirmed', true);
                        await this.$wire.restoreAssembledUpload(storedName);
                        this.progress = 'Finished. Read the message at the top of the screen, then log in again.';
                    } catch (error) {
                        this.progress = error.message || 'Upload failed.';
                    } finally {
                        this.busy = false;
                    }
                },
                async upload(file) {
                    const chunkBytes = {{ (int) $restoreChunkBytes }};
                    const total = Math.max(1, Math.ceil(file.size / chunkBytes));
                    const uploadId = [...crypto.getRandomValues(new Uint8Array(16))].map((byte) => byte.toString(16).padStart(2, '0')).join('');

                    for (let index = 0; index < total; index++) {
                        const startAt = index * chunkBytes;
                        const body = new FormData();
                        body.append('upload_id', uploadId);
                        body.append('index', String(index));
                        body.append('total', String(total));
                        body.append('original_name', file.name);
                        body.append('chunk', file.slice(startAt, startAt + chunkBytes), 'chunk.bin');

                        const response = await fetch('{{ route('admin.backups.restore-chunk') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body,
                        });
                        const data = await response.json().catch(() => ({}));

                        if (! response.ok) {
                            throw new Error(data.message || 'Upload failed.');
                        }

                        this.progress = 'Uploading… ' + Math.round(((index + 1) / total) * 100) + '%';

                        if (data.done && data.stored_name) {
                            return data.stored_name;
                        }
                    }

                    throw new Error('Upload did not finish.');
                },
            }"
        >
            <ol class="list-decimal space-y-1 pl-4 text-xs text-gray-600 dark:text-gray-400">
                <li>On this same server, click <strong>Restore this copy</strong> next to the zip in the list above.</li>
                <li>On a new install, put the old <strong>APP_KEY</strong> from the zip’s <code>app-key.txt</code> into <code>.env</code>, then log in as Super Admin.</li>
                <li>If the zip is on your PC or Google Drive, choose it below, tick the box, then click <strong>Upload and restore</strong>.</li>
                <li>When it finishes, log in again with the staff password that was in that backup.</li>
            </ol>

            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Backup zip from your computer or Google Drive</label>
                <input
                    x-ref="file"
                    type="file"
                    accept=".zip,application/zip"
                    class="mt-2 block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white dark:text-gray-200"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">A large school zip is sent in small pieces, so it is not limited to 12 MB.</p>
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                <input x-ref="confirm" type="checkbox" wire:model.live="restoreConfirmed" class="mt-1 rounded border-gray-300">
                <span>I understand this will <strong>replace all current data and files</strong> with the backup contents.</span>
            </label>

            @if ($pendingRestoreName)
                <div class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-950 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-100 dark:ring-amber-500/20">
                    The zip is already on the server. Put the old APP_KEY into .env, tick the box, then try again.
                    <button
                        type="button"
                        wire:click="restoreAssembledUpload({{ json_encode($pendingRestoreName) }})"
                        class="mt-2 inline-flex rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-600"
                    >
                        Try restore again
                    </button>
                </div>
            @endif

            <button
                type="button"
                x-on:click="start()"
                x-bind:disabled="busy"
                class="inline-flex rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-600 disabled:opacity-60"
            >
                <span x-show="! busy">Upload and restore</span>
                <span x-show="busy">Working… keep this tab open</span>
            </button>
            <p class="text-xs text-gray-600 dark:text-gray-300" x-text="progress"></p>
        </div>
    </div>
</div>
