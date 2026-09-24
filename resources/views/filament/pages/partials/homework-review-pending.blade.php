@php
    $desk = $desk ?? ['counts' => ['waiting' => 0, 'ready' => 0, 'sent' => 0, 'empty' => 0], 'groups' => []];
    $counts = $desk['counts'] ?? ['waiting' => 0, 'ready' => 0, 'sent' => 0, 'empty' => 0];
    $dateLabel = $dateLabel ?? '';
    $isToday = $isToday ?? false;
    $openBatchId = (int) ($openBatchId ?? 0);
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
                            @php
                                $batchId = (int) $section['batch_id'];
                                $isOpen = $openBatchId === $batchId;
                                $hasWaiting = (int) $section['submitted'] > 0;
                                $hasReady = (int) $section['approved'] > 0;
                                $hasSent = (int) $section['sent'] > 0;
                                $sectionTitle = filled($section['section']) && $section['section'] !== '—'
                                    ? (string) $section['section']
                                    : (string) $section['class_label'];
                            @endphp
                            <div @class([
                                'px-4 py-3',
                                'bg-primary-50/40 dark:bg-primary-500/10' => $isOpen,
                            ])>
                                <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        wire:click="toggleDeskSection({{ $batchId }})"
                                        class="flex min-w-0 flex-1 items-center gap-2 rounded-lg py-0.5 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                                    >
                                        <svg @class([
                                            'h-4 w-4 shrink-0 text-gray-400 transition-transform',
                                            'rotate-90' => $isOpen,
                                        ]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.17 10 7.23 6.29a.75.75 0 0 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $sectionTitle }}
                                            </span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                                @if ($hasWaiting)
                                                    {{ (int) $section['submitted'] }} waiting
                                                @elseif ($hasReady)
                                                    Ready to send
                                                @elseif ($hasSent)
                                                    Sent
                                                @else
                                                    No homework yet
                                                @endif
                                            </span>
                                        </span>
                                    </button>
                                    <div class="flex min-w-0 flex-wrap gap-2">
                                        @if ($hasWaiting)
                                            <button
                                                type="button"
                                                wire:click="approvePending({{ $batchId }})"
                                                class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500"
                                            >
                                                Approve pending
                                            </button>
                                        @endif
                                        @if ($hasReady)
                                            <button
                                                type="button"
                                                wire:click="sendCombinedForBatch({{ $batchId }})"
                                                wire:loading.attr="disabled"
                                                wire:target="sendCombinedForBatch({{ $batchId }})"
                                                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:opacity-50"
                                            >
                                                Send to parents
                                            </button>
                                        @elseif ($hasSent)
                                            <button
                                                type="button"
                                                wire:click="sendCombinedForBatch({{ $batchId }})"
                                                wire:loading.attr="disabled"
                                                wire:target="sendCombinedForBatch({{ $batchId }})"
                                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:bg-transparent dark:text-gray-200 dark:hover:bg-white/5 disabled:opacity-50"
                                            >
                                                Resend
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @if ($isOpen)
                                    @if (($section['items'] ?? []) === [])
                                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No subjects on this class yet.</p>
                                    @else
                                        <ul class="mt-3 divide-y divide-gray-100 rounded-xl border border-gray-100 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-900/40">
                                            @foreach ($section['items'] as $item)
                                                @if (filled($item['status_key']))
                                                    <li class="flex min-w-0 items-start justify-between gap-3 px-3 py-2.5">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['subject'] }}</p>
                                                            @if (filled($item['teacher']))
                                                                <p class="mt-1">
                                                                    <span class="inline-flex rounded-md bg-sky-50 px-1.5 py-0.5 text-xs font-semibold text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">{{ $item['teacher'] }}</span>
                                                                </p>
                                                            @endif
                                                            @if (filled($item['title']))
                                                                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ $item['title'] }}</p>
                                                            @endif
                                                        </div>
                                                        <div class="shrink-0 text-right">
                                                            <span @class([
                                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                                'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200' => $item['status_key'] === 'submitted',
                                                                'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200' => $item['status_key'] === 'approved',
                                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200' => $item['status_key'] === 'sent',
                                                            ])>
                                                                @if ($item['status_key'] === 'submitted')
                                                                    Waiting
                                                                @elseif ($item['status_key'] === 'approved')
                                                                    Ready
                                                                @elseif ($item['status_key'] === 'sent')
                                                                    Sent
                                                                @else
                                                                    {{ $item['status'] }}
                                                                @endif
                                                            </span>
                                                            @if ($item['submitted_at'])
                                                                <p class="mt-1 text-xs text-gray-400">{{ $item['submitted_at'] }}</p>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @else
                                                    <li class="flex min-w-0 items-baseline justify-between gap-3 px-3 py-1.5 text-xs text-gray-400">
                                                        <span>
                                                            {{ $item['subject'] }}
                                                            @if (filled($item['teacher']))
                                                                <span class="ml-1 inline-flex rounded-md bg-sky-50 px-1.5 py-0.5 font-semibold text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">{{ $item['teacher'] }}</span>
                                                            @endif
                                                        </span>
                                                        <span>No homework</span>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
