@php
    /** @var array<string, mixed> $status */
@endphp

<div class="space-y-5">
    <div @class([
        'fi-section rounded-2xl p-5 shadow-sm ring-1 sm:p-6',
        'ring-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' => $status['adms_enabled'] ?? false,
        'ring-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5' => ! ($status['adms_enabled'] ?? false),
    ])>
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">ADMS V1 (direct from machine)</p>
        <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">CRM is the cloud ADMS server</h2>
        <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-300">
            Point the ZKTeco device Cloud Server / ADMS URL to this CRM. Punches are stored raw, then mirrored into
            <code class="rounded bg-white/80 px-1.5 py-0.5 font-mono text-xs dark:bg-black/20">punch_logs</code>
            for the existing attendance processor. Attendance math is unchanged. Keep EasyTimePro until one machine is verified.
        </p>
        <div class="mt-4 rounded-xl bg-white/80 p-3 font-mono text-xs text-gray-800 ring-1 ring-gray-200 dark:bg-black/20 dark:text-gray-200 dark:ring-white/10">
            {{ $status['adms_url'] ?? url('/iclock') }}
        </div>
        <p class="mt-2 text-xs text-gray-500">
            Device paths used: <code>/iclock/cdata</code>, <code>/iclock/getrequest</code>
            · Active devices: <strong>{{ $status['active_device_count'] ?? 0 }}</strong>
            · Raw punches: <strong>{{ number_format($status['raw_punch_count'] ?? 0) }}</strong>
        </p>
        <p class="mt-2 text-xs text-amber-800 dark:text-amber-200">
            Machine clock: CRM sends <code>TimeZone=330</code> (IST) on handshake and again via
            <code>/iclock/getrequest</code> every minute. If the display shows UTC (~5h30m behind),
            pull latest, then reboot the device once so it picks up the command. Confirm Cloud Server
            points only to this CRM (not EasyWDMS at the same time).
        </p>
        <div class="mt-4">
            <a
                href="{{ \App\Filament\Resources\BiometricDevices\BiometricDeviceResource::getUrl() }}"
                class="inline-flex rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-500"
            >
                Manage biometric devices
            </a>
        </div>
    </div>

    @if (! empty($status['devices']))
        <div class="fi-section overflow-hidden rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Registered machines</h3>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($status['devices'] as $device)
                    <li class="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div>
                            <p class="font-semibold text-gray-950 dark:text-white">
                                {{ $device['name'] }}
                                @unless ($device['is_active'])
                                    <span class="ml-1 text-xs font-normal text-rose-600">inactive</span>
                                @endunless
                                @if (! empty($device['requires_face_verify']))
                                    <span class="ml-1 text-xs font-normal text-teal-700 dark:text-teal-300">face gate</span>
                                @endif
                            </p>
                            <p class="font-mono text-xs text-gray-500">{{ $device['serial'] }}</p>
                        </div>
                        <div class="text-xs text-gray-500">
                            Today: <strong>{{ $device['today'] }}</strong>
                            · Last seen:
                            {{ $device['last_seen_at'] ? \Illuminate\Support\Carbon::parse($device['last_seen_at'])->timezone(config('app.timezone'))->format('d M H:i') : '—' }}
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div @class([
        'fi-section rounded-2xl p-5 shadow-sm ring-1 sm:p-6',
        'ring-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' => $status['punch_table_ready'],
        'ring-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5' => ! $status['punch_table_ready'],
    ])>
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Legacy / bridge</p>
        <h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">
            @if ($status['punch_table_ready'])
                punch_logs ready
            @else
                punch_logs not found
            @endif
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            ADMS writes into <code class="font-mono text-xs">{{ $status['punch_table'] }}</code>, which
            <code class="font-mono text-xs">attendance:process-punches</code> already reads.
            EasyTimePro can still write the same table until ADMS is verified.
            @if ($status['punch_row_count'] !== null)
                <strong>{{ number_format($status['punch_row_count']) }}</strong> row(s) currently stored.
            @endif
        </p>
    </div>

    <div @class([
        'fi-section rounded-2xl p-5 shadow-sm ring-1 sm:p-6',
        'ring-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' => ($status['face_verify_enabled'] ?? false) && ($status['face_verify_health'] ?? '') === 'Healthy',
        'ring-amber-500/30 bg-amber-50/50 dark:bg-amber-500/5' => ($status['face_verify_enabled'] ?? false) && ($status['face_verify_health'] ?? '') !== 'Healthy',
        'ring-gray-950/5 dark:ring-white/10' => ! ($status['face_verify_enabled'] ?? false),
    ])>
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Face Verify gate</p>
        <h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">
            @if ($status['face_verify_enabled'] ?? false)
                Face Verify enabled
            @else
                Face Verify disabled
            @endif
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            RFID punches on gated devices wait for a signed PASS callback before writing
            <code class="font-mono text-xs">punch_logs</code>. Non-gated devices keep the current immediate attendance flow.
        </p>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-white/80 p-3 text-xs dark:bg-black/20">
                <dt class="text-gray-500">API URL</dt>
                <dd class="mt-1 break-all font-mono text-gray-900 dark:text-gray-100">{{ $status['face_verify_api_url'] ?: '—' }}</dd>
            </div>
            <div class="rounded-xl bg-white/80 p-3 text-xs dark:bg-black/20">
                <dt class="text-gray-500">Health</dt>
                <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $status['face_verify_health'] ?? '—' }}</dd>
            </div>
            <div class="rounded-xl bg-white/80 p-3 text-xs dark:bg-black/20">
                <dt class="text-gray-500">Callback URL</dt>
                <dd class="mt-1 break-all font-mono text-gray-900 dark:text-gray-100">{{ $status['face_verify_callback_url'] ?? url('/api/face-verify/approve') }}</dd>
            </div>
            <div class="rounded-xl bg-white/80 p-3 text-xs dark:bg-black/20">
                <dt class="text-gray-500">Pending verifications</dt>
                <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ number_format($status['face_verify_pending_count'] ?? 0) }}</dd>
            </div>
        </dl>
        <div class="mt-4 flex flex-wrap gap-2">
            <a
                href="{{ \App\Filament\Resources\FaceVerificationRequests\FaceVerificationRequestResource::getUrl() }}"
                class="inline-flex rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-500"
            >
                Review face verifications
            </a>
            <a
                href="{{ \App\Filament\Resources\BiometricDevices\BiometricDeviceResource::getUrl() }}"
                class="inline-flex rounded-lg bg-gray-900/5 px-3 py-2 text-xs font-semibold text-gray-900 hover:bg-gray-900/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/20"
            >
                Configure gated devices
            </a>
        </div>
    </div>

    <div class="fi-section rounded-2xl p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:p-6">
        <h3 class="text-base font-bold text-gray-950 dark:text-white">Setup checklist</h3>
        <ol class="mt-4 space-y-4 text-sm text-gray-700 dark:text-gray-300">
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">1</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Register device serial</p>
                    <p class="mt-0.5">Setup → Biometric devices → add name + SN from the K40 Pro.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">2</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Point machine ADMS to CRM</p>
                    <p class="mt-0.5">Cloud server address: <code class="font-mono text-xs">{{ $status['adms_url'] ?? url('/iclock') }}</code> (HTTPS recommended).</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">3</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Match roll numbers</p>
                    <p class="mt-0.5">{{ $rollHint }}</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">4</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Keep scheduler running</p>
                    <p class="mt-0.5"><code class="rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-white/10">{{ $status['processor_command'] }}</code> via cron <code class="font-mono text-xs">schedule:run</code>.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-xs font-bold text-primary-700 dark:text-primary-300">5</span>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Keep EasyTimePro until verified</p>
                    <p class="mt-0.5">Do not remove EasyWDMS until punches appear correctly from ADMS for one live machine.</p>
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
