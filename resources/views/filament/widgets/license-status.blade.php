@php
    $styles = match ($level) {
        'critical' => [
            'container' => 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950/40',
            'icon' => 'text-danger-600 dark:text-danger-400',
            'title' => 'text-danger-900 dark:text-danger-100',
            'body' => 'text-danger-800 dark:text-danger-200',
            'badge' => 'bg-danger-100 text-danger-800 dark:bg-danger-900/60 dark:text-danger-100',
        ],
        'warning' => [
            'container' => 'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950/30',
            'icon' => 'text-warning-600 dark:text-warning-400',
            'title' => 'text-warning-900 dark:text-warning-100',
            'body' => 'text-warning-800 dark:text-warning-200',
            'badge' => 'bg-warning-100 text-warning-800 dark:bg-warning-900/60 dark:text-warning-100',
        ],
        default => [
            'container' => 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900',
            'icon' => 'text-primary-600 dark:text-primary-400',
            'title' => 'text-gray-950 dark:text-white',
            'body' => 'text-gray-600 dark:text-gray-300',
            'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        ],
    };
@endphp

<x-filament-widgets::widget>
    <div class="rounded-xl border p-4 shadow-sm {{ $styles['container'] }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-1 gap-3">
                <x-filament::icon
                    :icon="$show_warning ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-shield-check'"
                    class="mt-0.5 h-6 w-6 shrink-0 {{ $styles['icon'] }}"
                />
                <div class="min-w-0">
                    <p class="text-sm font-semibold {{ $styles['title'] }}">
                        @if ($show_warning)
                            Software licence expiring soon
                        @else
                            Software licence
                        @endif
                    </p>

                    @if ($show_warning && $days_remaining !== null)
                        <p class="mt-1 text-sm {{ $styles['body'] }}">
                            Your licence ends in <strong>{{ $days_remaining }} {{ str('day')->plural($days_remaining) }}</strong>
                            @if ($expires_at_label)
                                on <strong>{{ $expires_at_label }}</strong>
                            @endif
                            . Contact your software provider to renew before access is locked.
                        </p>
                    @else
                        <p class="mt-1 text-sm {{ $styles['body'] }}">
                            Valid until
                            <strong>{{ $expires_at_label ?? '—' }}</strong>
                            @if ($days_remaining !== null)
                                ({{ $days_remaining }} {{ str('day')->plural($days_remaining) }} remaining)
                            @endif
                            · <strong>{{ $plan_label }}</strong>
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
</x-filament-widgets::widget>
