@php
    $desk = $desk ?? ['counts' => ['waiting' => 0, 'ready' => 0, 'sent' => 0, 'empty' => 0], 'groups' => []];
    $counts = $desk['counts'] ?? ['waiting' => 0, 'ready' => 0, 'sent' => 0, 'empty' => 0];
    $dateLabel = $dateLabel ?? '';
    $isToday = $isToday ?? false;
    $selectedBatchId = (int) ($selectedBatchId ?? 0);
    $waiting = (int) ($counts['waiting'] ?? 0);
    $ready = (int) ($counts['ready'] ?? 0);
    $sent = (int) ($counts['sent'] ?? 0);
    $empty = (int) ($counts['empty'] ?? 0);
@endphp

<div class="space-y-3">
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ $dateLabel }}
            @if ($isToday)
                · today
            @endif
        </p>
        <div class="flex min-w-0 flex-wrap gap-x-3 gap-y-1 text-xs font-semibold">
            <a href="{{ $submitUrl }}" class="text-primary-600 hover:underline dark:text-primary-400">Submit</a>
            <a href="{{ $checkUrl }}" class="text-primary-600 hover:underline dark:text-primary-400">Check completion</a>
            <a href="{{ $historyUrl }}" class="text-primary-600 hover:underline dark:text-primary-400">History</a>
        </div>
    </div>

    <div class="flex min-w-0 flex-wrap gap-2 text-xs font-medium">
        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Waiting: {{ $waiting }}</span>
        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">Ready to send: {{ $ready }}</span>
        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Sent: {{ $sent }}</span>
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600 dark:bg-white/5 dark:text-gray-300">No homework: {{ $empty }}</span>
    </div>

    @if ($waiting === 0 && $ready === 0 && $sent === 0)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            @if ($isToday)
                No homework for today ({{ $dateLabel }}).
            @else
                No homework for {{ $dateLabel }}.
            @endif
        </div>
    @endif

    @if (($desk['groups'] ?? []) === [])
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            No active classes yet.
        </div>
    @else
        <div class="space-y-4">
            @foreach ($desk['groups'] as $course)
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
                                            @if ((int) $section['submitted'] > 0)
                                                · {{ (int) $section['submitted'] }} waiting
                                            @elseif ((int) $section['approved'] > 0)
                                                · {{ (int) $section['approved'] }} ready to send
                                            @elseif ((int) $section['sent'] > 0)
                                                · sent
                                            @else
                                                · no homework yet
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex min-w-0 flex-wrap gap-2">
                                        @if ((int) $section['submitted'] > 0)
                                            <button
                                                type="button"
                                                wire:click="approvePending({{ (int) $section['batch_id'] }})"
                                                class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500"
                                            >
                                                Approve pending
                                            </button>
                                        @endif
                                        @if ((int) $section['approved'] > 0 || (int) $section['sent'] > 0)
                                            <button
                                                type="button"
                                                wire:click="sendCombinedForBatch({{ (int) $section['batch_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="sendCombinedForBatch({{ (int) $section['batch_id'] }})"
                                                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:opacity-50"
                                            >
                                                Send to parents
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @if (($section['items'] ?? []) === [])
                                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No homework yet.</p>
                                @else
                                    <ul class="mt-3 space-y-2">
                                        @foreach ($section['items'] as $item)
                                            <li class="flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-1 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $item['teacher'] }}</span>
                                                <span class="text-gray-500 dark:text-gray-400">{{ $item['subject'] }}</span>
                                                <span class="min-w-0 grow basis-full truncate text-gray-600 sm:basis-auto dark:text-gray-300">{{ $item['title'] }}</span>
                                                @if ($item['status_key'])
                                                    <span @class([
                                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200' => $item['status_key'] === 'submitted',
                                                        'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200' => $item['status_key'] === 'approved',
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200' => $item['status_key'] === 'sent',
                                                    ])>{{ $item['status'] }}</span>
                                                @endif
                                                @if ($item['submitted_at'])
                                                    <span class="text-xs text-gray-400">{{ $item['submitted_at'] }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
