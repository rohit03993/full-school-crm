@php
    $days = $this->days();
    $total = array_sum(array_map(fn (array $day): int => count($day['people']), $days));
    $badges = [
        'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        'violet' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    ];
@endphp

<div class="space-y-4">
    <section class="crm-ops-strip">
        <div class="crm-ops-strip__head">
            <div>
                <p class="crm-ops-strip__heading">Next 7 days</p>
                <p class="crm-ops-strip__sub">{{ $this->rangeLabel() }} · {{ $total }} {{ $total === 1 ? 'person' : 'people' }}</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-300">Student birthdays, staff birthdays, and staff anniversaries. Today is first. Tap a name to open the profile.</p>
    </section>

    @if ($days === [])
        <section class="rounded-2xl bg-white px-4 py-10 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">No birthdays or anniversaries in the next 7 days.</p>
        </section>
    @else
        @foreach ($days as $day)
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $day['label'] }}</p>
                    @if ($day['is_today'])
                        <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-800 dark:bg-rose-500/15 dark:text-rose-200">Today</span>
                    @endif
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($day['people'] as $person)
                        <li class="flex items-center justify-between gap-3 px-4 py-2">
                            @if (filled($person['url']))
                                <a href="{{ $person['url'] }}" wire:navigate class="flex min-h-11 items-center text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $person['name'] }}
                                </a>
                            @else
                                <span class="flex min-h-11 items-center text-sm font-medium text-gray-950 dark:text-white">{{ $person['name'] }}</span>
                            @endif
                            <span @class(['inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold', $badges[$person['tone']]])>
                                {{ $person['occasion'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
