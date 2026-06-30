@php
    /** @var array{punch_table: string, punch_table_ready: bool, notification_queue_ready: bool, last_processed_punch_id: int, punch_row_count: int|null, processor_command: string} $status */
@endphp

<div class="space-y-5">
    <div @class([
        'fi-section rounded-2xl p-5 shadow-sm ring-1 sm:p-6',
        'ring-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' => $status['punch_table_ready'],
        'ring-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5' => ! $status['punch_table_ready'],
    ])>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Connection status</p>
                <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                    @if ($status['punch_table_ready'])
                        Biometric table connected
                    @else
                        Biometric table not found
                    @endif
                </h2>
                <p class="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-300">
                    @if ($status['punch_table_ready'])
                        The CRM can read <code class="rounded bg-white/80 px-1.5 py-0.5 font-mono text-xs dark:bg-black/20">{{ $status['punch_table'] }}</code>
                        on this database.
                        @if ($status['punch_row_count'] !== null)
                            <strong>{{ number_format($status['punch_row_count']) }}</strong> punch row(s) stored.
                        @endif
                    @else
                        EasyTimePro normally writes punches to a MySQL table called <code class="rounded bg-white/80 px-1.5 py-0.5 font-mono text-xs dark:bg-black/20">punch_logs</code>
                        on the <strong>same server</strong> as this CRM. Until that table exists, live punches will not appear — but
                        <a href="{{ $attendanceUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Manual batch</a> still works.
                    @endif
                </p>
            </div>
            @if ($status['punch_table_ready'])
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white">
                    <span class="h-2 w-2 rounded-full bg-white"></span> Ready
                </span>
            @else
                <span class="inline-flex items-center gap-2 rounded-full bg-amber-600 px-3 py-1.5 text-xs font-bold text-white">Setup required</span>
            @endif
        </div>
    </div>

    <div class="fi-section rounded-2xl p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:p-6">
        <h3 class="text-base font-bold text-gray-950 dark:text-white">Why you see the warning on Attendance</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            The Attendance page checks whether <code class="font-mono text-xs">{{ $status['punch_table'] }}</code> exists in <strong>this CRM database</strong>.
            EasyTimePro is a separate app — its punches only show here when that table is on the same MySQL server (or replicated into this DB).
        </p>
    </div>

    <div class="fi-section rounded-2xl p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:p-6">
        <h3 class="text-base font-bold text-gray-950 dark:text-white">Setup checklist (server admin)</h3>
        <ol class="mt-4 space-y-4 text-sm text-gray-700 dark:text-gray-300">
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">1</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Install EasyTimePro</p>
                    <p class="mt-0.5">Biometric device writes each scan to MySQL table <code class="font-mono text-xs">punch_logs</code> (columns include <code class="font-mono text-xs">employee_id</code>, <code class="font-mono text-xs">punch_date</code>, <code class="font-mono text-xs">punch_time</code>).</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">2</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Same database server</p>
                    <p class="mt-0.5">CRM and EasyTimePro must use the same MySQL instance so <code class="font-mono text-xs">punch_logs</code> is visible to this app.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">3</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Run CRM migrations</p>
                    <p class="mt-0.5"><code class="rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-white/10">php artisan migrate</code></p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">4</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Start punch processor</p>
                    <p class="mt-0.5"><code class="rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-white/10">{{ $status['processor_command'] }}</code></p>
                    <p class="mt-1 text-xs text-gray-500">Or rely on Laravel scheduler every minute if cron is configured.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">5</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Match roll numbers</p>
                    <p class="mt-0.5">{{ $rollHint }}</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">6</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Configure parent WhatsApp</p>
                    <p class="mt-0.5">
                        Choose separate templates for machine punches (<code>punch_logs</code>) and staff manual IN/OUT in
                        <a href="{{ $whatsappSettingsUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Settings → WhatsApp Settings</a>.
                    </p>
                </div>
            </li>
        </ol>
    </div>

    @if ($status['punch_table_ready'])
        <div class="fi-section rounded-2xl p-5 text-sm shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:p-6">
            <h3 class="font-bold text-gray-950 dark:text-white">Processor state</h3>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                    <dt class="text-xs text-gray-500">Last processed punch ID</dt>
                    <dd class="mt-1 font-mono font-bold text-gray-950 dark:text-white">{{ number_format($status['last_processed_punch_id']) }}</dd>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                    <dt class="text-xs text-gray-500">Optional notification queue</dt>
                    <dd class="mt-1 font-semibold {{ $status['notification_queue_ready'] ? 'text-emerald-600' : 'text-gray-500' }}">
                        {{ $status['notification_queue_ready'] ? 'Installed' : 'Not installed (optional)' }}
                    </dd>
                </div>
            </dl>
        </div>
    @endif
</div>
