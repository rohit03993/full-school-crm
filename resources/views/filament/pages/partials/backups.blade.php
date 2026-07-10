<div class="mx-auto max-w-3xl space-y-6 pb-24 lg:pb-6">
    <div class="rounded-2xl bg-sky-50 px-4 py-4 text-sm text-sky-950 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-100 dark:ring-sky-500/20">
        <p class="font-semibold">Full institute backup (disaster recovery)</p>
        <p class="mt-1 text-sky-900/90 dark:text-sky-200/90">
            Each zip includes the <strong>entire database</strong> plus <strong>all uploaded files</strong>:
            student photos, Aadhaar, documents, fee receipts, ID cards, homework, website logos/gallery,
            WhatsApp media, cases, call logs, attendance, fees — everything needed to restore the CRM as it was.
        </p>
        <p class="mt-2 text-xs text-sky-800 dark:text-sky-300">
            Daily automatic backup runs at {{ $scheduleAt }} (server time). Latest {{ $retain }} archives are kept.
            Copy downloads off the server (Drive / USB). Restore only with matching <code class="rounded bg-white/70 px-1 dark:bg-black/30">APP_KEY</code>.
        </p>
    </div>

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
            Large institutes may take a few minutes. Keep this tab open until it finishes.
        </p>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
            <h2 class="text-sm font-bold text-gray-950 dark:text-white">Available backups</h2>
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

    <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-xs text-gray-600 dark:border-white/15 dark:text-gray-400">
        <p class="font-semibold text-gray-800 dark:text-gray-200">Restore (server)</p>
        <p class="mt-1">After a wipe, install the CRM, set <code>APP_KEY</code> from the zip’s <code>app-key.txt</code>, then run:</p>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-950 px-3 py-2 text-[11px] text-gray-100">php artisan crm:restore path/to/school-crm-full-backup-….zip --force</pre>
    </div>
</div>
