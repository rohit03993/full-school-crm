@php
    $pending = $pending ?? ['total' => 0, 'groups' => []];
    $dateLabel = $dateLabel ?? '';
    $isToday = $isToday ?? false;
    $selectedBatchId = (int) ($selectedBatchId ?? 0);
@endphp

<div class="space-y-3">
    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ $dateLabel }}
        @if ($isToday)
            · today
        @endif
        · {{ (int) $pending['total'] }} waiting for approval
    </p>

    @if ((int) $pending['total'] === 0)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            @if ($isToday)
                No homework for today ({{ $dateLabel }}).
            @else
                No pending homework for {{ $dateLabel }}.
            @endif
        </div>
    @else
        <div class="space-y-4">
            @foreach ($pending['groups'] as $course)
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-white/5 dark:bg-white/5">
                        <h3 class="text-sm font-bold text-gray-950 dark:text-white">{{ $course['course_name'] }}</h3>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($course['sections'] as $section)
                            <div @class([
                                'px-4 py-3',
                                'bg-primary-50/60 dark:bg-primary-500/10' => $selectedBatchId === (int) $section['batch_id'],
                            ])>
                                <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $section['class_label'] }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Section {{ $section['section'] }}
                                            · {{ count($section['items']) }} subject{{ count($section['items']) === 1 ? '' : 's' }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="openClass({{ (int) $section['batch_id'] }})"
                                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                                    >
                                        Open class
                                    </button>
                                </div>

                                <ul class="mt-3 space-y-2">
                                    @foreach ($section['items'] as $item)
                                        <li class="flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-1 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $item['teacher'] }}</span>
                                            <span class="text-gray-500 dark:text-gray-400">{{ $item['subject'] }}</span>
                                            <span class="min-w-0 grow basis-full truncate text-gray-600 sm:basis-auto dark:text-gray-300">{{ $item['title'] }}</span>
                                            @if ($item['submitted_at'])
                                                <span class="text-xs text-gray-400">{{ $item['submitted_at'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
