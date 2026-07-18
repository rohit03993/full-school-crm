@php
    /** @var array<string, mixed> $status */
    $connected = (bool) ($status['connected'] ?? false);
    $devices = $status['devices'] ?? [];
@endphp

<div class="mb-6 space-y-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-sm font-bold text-gray-950 dark:text-white">Connection status</p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Vendor adds this school on the Face website → you paste the client code here → APK uses Face URL + device number.
            </p>
        </div>
        @if ($connected)
            <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">Connected</span>
        @else
            <span class="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-bold text-amber-800 dark:text-amber-200">Not connected</span>
        @endif
    </div>

    @if ($connected)
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">School on Face</dt>
                <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $status['tenant_name'] ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Client code</dt>
                <dd class="mt-0.5 font-mono font-semibold text-gray-950 dark:text-white">{{ $status['client_code'] ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Face Platform URL</dt>
                <dd class="mt-0.5 break-all font-mono text-sm text-gray-950 dark:text-white">{{ $status['api_url'] ?: '—' }}</dd>
            </div>
        </dl>

        <div class="rounded-xl bg-slate-50 p-4 dark:bg-white/5">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">APK Settings (same Face URL)</p>
            <ul class="mt-2 space-y-1 text-sm text-gray-800 dark:text-gray-200">
                @forelse ($devices as $device)
                    <li>
                        Device no: <span class="font-mono font-bold">{{ $device['id'] }}</span>
                        · {{ $device['name'] }}
                        <span class="text-xs text-gray-500">(token was shown once when the client was created on Face admin)</span>
                    </li>
                @empty
                    <li class="text-amber-700 dark:text-amber-300">No devices yet — ask vendor to add a device on Face Platform.</li>
                @endforelse
            </ul>
        </div>
    @endif

    <p class="text-xs text-gray-500 dark:text-gray-400">
        ADMS / RFID machines stay under
        <a href="{{ $biometricUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Biometric setup</a>.
    </p>
</div>
