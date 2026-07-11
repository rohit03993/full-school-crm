@php
    /** @var string $admsUrl */
    /** @var bool $admsEnabled */
    /** @var string $overall */
    /** @var int $online */
    /** @var int $active */
    /** @var int $total */
    /** @var int $todayPunches */
    /** @var \Illuminate\Support\Carbon|null $latestSeen */
    /** @var int $onlineWindowMinutes */

    $tone = match ($overall) {
        'online' => 'emerald',
        'idle' => 'amber',
        'none' => 'gray',
        default => 'rose',
    };
    $headline = match ($overall) {
        'online' => 'ADMS receiver is working',
        'idle' => 'ADMS reachable earlier — waiting for next poll',
        'none' => 'No active biometric devices',
        default => 'No device seen recently — check machine network / cloud',
    };
    $detail = match ($overall) {
        'online' => "{$online} of {$active} active machine(s) polled CRM within the last {$onlineWindowMinutes} minutes.",
        'idle' => 'Cloud link worked recently, but nothing checked in during the last '.$onlineWindowMinutes.' minutes. Reboot the device or confirm Wi‑Fi/cloud icon has no red X.',
        'none' => 'Add a device and turn Active on before the machine can push punches.',
        default => 'Last contact: '.($latestSeen?->timezone(config('app.timezone'))->format('d M Y H:i') ?? 'never').'. Fix DNS/Wi‑Fi until the cloud icon clears, then wait 1–2 minutes.',
    };
@endphp

<div @class([
    'mb-4 rounded-2xl p-4 ring-1 sm:p-5',
    'bg-emerald-50/80 ring-emerald-500/25 dark:bg-emerald-500/10 dark:ring-emerald-500/30' => $tone === 'emerald',
    'bg-amber-50/80 ring-amber-500/25 dark:bg-amber-500/10 dark:ring-amber-500/30' => $tone === 'amber',
    'bg-rose-50/80 ring-rose-500/25 dark:bg-rose-500/10 dark:ring-rose-500/30' => $tone === 'rose',
    'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => $tone === 'gray',
])>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                ADMS cloud receiver
                @unless ($admsEnabled)
                    <span class="ml-1 text-rose-600">· disabled in config</span>
                @endunless
            </p>
            <h2 @class([
                'mt-1 text-lg font-bold',
                'text-emerald-900 dark:text-emerald-200' => $tone === 'emerald',
                'text-amber-900 dark:text-amber-200' => $tone === 'amber',
                'text-rose-900 dark:text-rose-200' => $tone === 'rose',
                'text-gray-950 dark:text-white' => $tone === 'gray',
            ])>
                {{ $headline }}
            </h2>
            <p class="mt-1 max-w-3xl text-sm text-gray-700 dark:text-gray-300">{{ $detail }}</p>
            <p class="mt-2 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                {{ $admsUrl }}
                <span class="text-gray-400">·</span> /cdata + /getrequest
            </p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <span class="rounded-lg bg-white/90 px-2.5 py-1.5 text-xs font-semibold text-gray-800 ring-1 ring-gray-200 dark:bg-black/20 dark:text-gray-100 dark:ring-white/10">
                Online now {{ $online }}/{{ $active }}
            </span>
            <span class="rounded-lg bg-white/90 px-2.5 py-1.5 text-xs font-semibold text-gray-800 ring-1 ring-gray-200 dark:bg-black/20 dark:text-gray-100 dark:ring-white/10">
                Devices {{ $total }}
            </span>
            <span class="rounded-lg bg-white/90 px-2.5 py-1.5 text-xs font-semibold text-gray-800 ring-1 ring-gray-200 dark:bg-black/20 dark:text-gray-100 dark:ring-white/10">
                Punches today {{ $todayPunches }}
            </span>
        </div>
    </div>
</div>
