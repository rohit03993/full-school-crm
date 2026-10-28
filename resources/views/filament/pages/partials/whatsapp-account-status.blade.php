@php
    $account = $account ?? [];
    $ok = (bool) ($account['ok'] ?? false);
    $quality = (string) ($account['quality_code'] ?? '');
    $qualityClass = match ($quality) {
        'GREEN' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
        'YELLOW' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
        'RED' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200',
        default => 'border-gray-200 bg-white text-gray-800 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100',
    };
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-gray-950 dark:text-white">Account status</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Quality and the people limit come from Meta. The people count is from this CRM for the last 24 hours.
            </p>
        </div>
        <button
            type="button"
            wire:click="refreshAccountStatus"
            wire:loading.attr="disabled"
            class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
        >
            <span wire:loading.remove wire:target="refreshAccountStatus">Check again</span>
            <span wire:loading wire:target="refreshAccountStatus">Checking…</span>
        </button>
    </div>

    @if (! $ok)
        <p class="mt-4 text-sm text-gray-700 dark:text-gray-200">{{ $account['message'] ?? 'Account status is not available.' }}</p>
    @else
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Number</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $account['connection_label'] }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $account['display_phone_number'] ?: '—' }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $account['verified_name'] ?: '—' }}</p>
            </div>
            <div class="rounded-xl border p-4 {{ $qualityClass }}">
                <p class="text-xs font-bold uppercase tracking-wide">Quality</p>
                <p class="mt-1 text-lg font-bold">{{ $account['quality_label'] }}</p>
                <p class="mt-1 text-sm">Green is good. Yellow is falling. Red is poor.</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">People limit</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $account['limit_label'] }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Different people you may start a business message to in 24 hours.</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Started by us</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ number_format((int) $account['people_started']) }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Different people this CRM messaged with a template in the last 24 hours.</p>
            </div>
        </div>
        @if (! empty($account['checked_at']))
            <p class="mt-3 text-xs text-gray-500">Last checked {{ $account['checked_at'] }}. Meta’s answer is kept for 10 minutes.</p>
        @endif
    @endif
</div>
