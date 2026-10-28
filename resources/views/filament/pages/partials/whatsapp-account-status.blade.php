@php
    $account = $account ?? [];
    $ok = (bool) ($account['ok'] ?? false);
    $quality = (string) ($account['quality_code'] ?? '');
    $limitMax = match ((string) ($account['limit_code'] ?? '')) {
        'TIER_50' => 50,
        'TIER_250' => 250,
        'TIER_1K' => 1000,
        'TIER_2K' => 2000,
        'TIER_10K' => 10000,
        'TIER_100K' => 100000,
        default => null,
    };
    $people = (int) ($account['people_started'] ?? 0);
    $usedExact = $limitMax ? min(100, ($people / $limitMax) * 100) : 0;
    $usedLabel = $usedExact >= 10 ? (string) (int) round($usedExact) : number_format($usedExact, 1);
    $usedWidth = $people > 0 ? max($usedExact, 1.5) : 0;
    $qualityBorder = match ($quality) {
        'GREEN' => 'border-l-emerald-500',
        'YELLOW' => 'border-l-amber-500',
        'RED' => 'border-l-red-500',
        default => 'border-l-gray-300',
    };
    $qualityBar = match ($quality) {
        'YELLOW' => 'bg-amber-500',
        'RED' => 'bg-red-500',
        default => 'bg-emerald-500',
    };
    $connectionClass = match ((string) ($account['connection_label'] ?? '')) {
        'Connected' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
        'Flagged', 'Rate limited', 'Pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        'Restricted', 'Banned', 'Disconnected' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-200',
        default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200',
    };
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            @if ($ok)
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-lg font-bold text-gray-950 dark:text-white">{{ $account['verified_name'] ?: 'WhatsApp' }}</h2>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $connectionClass }}">{{ $account['connection_label'] }}</span>
                </div>
                <p class="mt-1 font-mono text-sm text-gray-600 dark:text-gray-300">{{ $account['display_phone_number'] ?: '—' }}</p>
            @else
                <h2 class="text-lg font-bold text-gray-950 dark:text-white">Account status</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $account['message'] ?? 'Account status is not available.' }}</p>
            @endif
        </div>
        <div class="flex flex-col items-stretch gap-1 sm:items-end">
            <button
                type="button"
                wire:click="refreshAccountStatus"
                wire:loading.attr="disabled"
                class="rounded-xl bg-gray-950 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
            >
                <span wire:loading.remove wire:target="refreshAccountStatus">Check again</span>
                <span wire:loading wire:target="refreshAccountStatus">Checking…</span>
            </button>
            @if ($ok && ! empty($account['checked_at']))
                <p class="text-xs text-gray-500">Checked {{ $account['checked_at'] }}</p>
            @endif
        </div>
    </div>

    @if ($ok)
        <div class="crm-meta-wa-log__stats mt-4">
            <div class="crm-meta-wa-stat {{ $qualityBorder }}">
                <span class="crm-meta-wa-stat__value">{{ $account['quality_label'] }}</span>
                <span class="crm-meta-wa-stat__label">Quality</span>
            </div>
            <div class="crm-meta-wa-stat border-l-primary-500">
                <span class="crm-meta-wa-stat__value">{{ $limitMax ? number_format($limitMax) : $account['limit_label'] }}</span>
                <span class="crm-meta-wa-stat__label">People in 24 hours</span>
            </div>
            <div class="crm-meta-wa-stat border-l-sky-500">
                <span class="crm-meta-wa-stat__value">{{ number_format($people) }}</span>
                <span class="crm-meta-wa-stat__label">Sent by us</span>
            </div>
        </div>

        @if ($limitMax)
            <div class="mt-4">
                <div class="mb-1 flex items-center justify-between text-xs font-semibold text-gray-500">
                    <span>{{ number_format($people) }} of {{ number_format($limitMax) }} people</span>
                    <span>{{ $usedLabel }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-2 rounded-full {{ $qualityBar }}" style="width: {{ $usedWidth }}%"></div>
                </div>
            </div>
        @endif
    @endif
</div>
