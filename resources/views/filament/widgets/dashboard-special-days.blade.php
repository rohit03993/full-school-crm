@php
    $tones = [
        'rose' => 'border-rose-200 bg-rose-50/40 dark:border-rose-500/20 dark:bg-rose-500/5',
        'amber' => 'border-amber-200 bg-amber-50/70 dark:border-amber-500/20 dark:bg-amber-500/10',
        'violet' => 'border-violet-200 bg-violet-50/50 dark:border-violet-500/20 dark:bg-violet-500/5',
    ];
    $badges = [
        'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        'violet' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    ];
@endphp

<x-filament-widgets::widget>
    <section class="crm-ops-strip">
        <div class="crm-ops-strip__head">
            <div>
                <p class="crm-ops-strip__heading">Today's special days</p>
                <p class="crm-ops-strip__sub">{{ $dateLabel }} · Tap a name to open the profile</p>
            </div>
            @if (filled($wishesUrl))
                <a href="{{ $wishesUrl }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    Next 7 days
                </a>
            @endif
        </div>

        <div class="grid gap-3 lg:grid-cols-3">
            @foreach ($groups as $group)
                <div @class(['flex flex-col rounded-2xl border p-3', $tones[$group['tone']]])>
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group['label'] }}</p>
                        <span @class(['inline-flex min-w-8 items-center justify-center rounded-full px-2 py-0.5 text-xs font-bold', $badges[$group['tone']]])>
                            {{ count($group['people']) }}
                        </span>
                    </div>

                    @if ($group['people'] === [])
                        <p class="mt-3 px-1 text-sm text-gray-500 dark:text-gray-400">None today</p>
                    @else
                        <ul class="mt-2 max-h-72 space-y-1 overflow-y-auto">
                            @foreach ($group['people'] as $person)
                                <li>
                                    @if (filled($person['url']))
                                        <a
                                            href="{{ $person['url'] }}"
                                            wire:navigate
                                            class="flex min-h-11 flex-col justify-center rounded-xl px-2 py-1 text-sm font-medium text-gray-950 hover:bg-white dark:text-white dark:hover:bg-white/10"
                                        >
                                            <span>{{ $person['name'] }}</span>
                                            @if (filled($person['place'] ?? null))
                                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $person['place'] }}</span>
                                            @endif
                                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $person['message'] }}</span>
                                        </a>
                                    @else
                                        <span class="flex min-h-11 flex-col justify-center px-2 py-1 text-sm font-medium text-gray-950 dark:text-white">
                                            <span>{{ $person['name'] }}</span>
                                            @if (filled($person['place'] ?? null))
                                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $person['place'] }}</span>
                                            @endif
                                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $person['message'] }}</span>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
