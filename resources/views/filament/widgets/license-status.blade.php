@php
    $isExpired = $level === 'expired';
    $isAlert = $show_warning || $isExpired;

    $styles = match (true) {
        $isExpired, $level === 'critical' => [
            'container' => 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950/40',
            'icon' => 'text-danger-600 dark:text-danger-400',
            'title' => 'text-danger-900 dark:text-danger-100',
            'body' => 'text-danger-800 dark:text-danger-200',
            'badge' => 'bg-danger-100 text-danger-800 dark:bg-danger-900/60 dark:text-danger-100',
        ],
        default => [
            'container' => 'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950/30',
            'icon' => 'text-warning-600 dark:text-warning-400',
            'title' => 'text-warning-900 dark:text-warning-100',
            'body' => 'text-warning-800 dark:text-warning-200',
            'badge' => 'bg-warning-100 text-warning-800 dark:bg-warning-900/60 dark:text-warning-100',
        ],
    };
@endphp

<x-filament-widgets::widget>
    @if ($isAlert)
        <div class="rounded-2xl border p-4 shadow-sm {{ $styles['container'] }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 flex-1 gap-3">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-triangle"
                        class="mt-0.5 h-6 w-6 shrink-0 {{ $styles['icon'] }}"
                    />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold {{ $styles['title'] }}">
                            {{ $isExpired ? 'Software licence expired' : 'Software licence expiring soon' }}
                        </p>

                        @if ($isExpired)
                            <p class="mt-1 text-sm {{ $styles['body'] }}">
                                Access is limited until the licence is renewed. Contact your software provider.
                            </p>
                        @elseif ($days_remaining !== null)
                            <p class="mt-1 text-sm {{ $styles['body'] }}">
                                Your licence ends in <strong>{{ $days_remaining }} {{ str('day')->plural($days_remaining) }}</strong>
                                @if ($expires_at_label)
                                    on <strong>{{ $expires_at_label }}</strong>
                                @endif
                                . Contact your software provider to renew before access is locked.
                            </p>
                        @endif
                    </div>
                </div>

                @if ($expires_at_label)
                    <span class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold {{ $styles['badge'] }}">
                        Valid till {{ $expires_at_label }}
                    </span>
                @endif
            </div>
        </div>
    @else
        {{-- Healthy licence is reference information, so it stays a quiet strip --}}
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 px-1 text-[11px] text-gray-500 dark:text-gray-400">
            <x-filament::icon icon="heroicon-m-shield-check" class="h-3.5 w-3.5 shrink-0 text-emerald-500" />
            <span class="font-semibold uppercase tracking-[0.08em]">Licence active</span>
            <span aria-hidden="true">·</span>
            <span>
                Valid till {{ $expires_at_label ?? '—' }}
                @if ($days_remaining !== null)
                    ({{ $days_remaining }} {{ str('day')->plural($days_remaining) }} left)
                @endif
            </span>
            <span aria-hidden="true">·</span>
            <span>{{ $plan_label }}</span>
        </div>
    @endif
</x-filament-widgets::widget>
