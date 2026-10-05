@php
    $desk = $desk ?? ['counts' => ['waiting' => 0, 'ready' => 0, 'sent' => 0, 'empty' => 0], 'groups' => []];
    $counts = $desk['counts'] ?? ['waiting' => 0, 'ready' => 0, 'sent' => 0, 'empty' => 0];
    $dateLabel = $dateLabel ?? '';
    $isToday = $isToday ?? false;
    $canEnter = (bool) ($canEnter ?? false);
    $canSend = (bool) ($canSend ?? false);
    $windowNote = $windowNote ?? null;
    $openBatchId = (int) ($openBatchId ?? 0);
    $waiting = (int) ($counts['waiting'] ?? 0);
    $ready = (int) ($counts['ready'] ?? 0);
    $sent = (int) ($counts['sent'] ?? 0);
    $empty = (int) ($counts['empty'] ?? 0);
    $left = (int) ($counts['left'] ?? 0);
    $sendConfirmBatchId = (int) ($sendConfirmBatchId ?? 0);
    $duplicateSendBatchId = (int) ($duplicateSendBatchId ?? 0);
    $duplicateSendLabel = $duplicateSendLabel ?? 'This class';
    $sendReportUrl = $sendReportUrl ?? null;
    $missingSubjects = $missingSubjects ?? [];
    $missingSubjectReasons = $missingSubjectReasons ?? [];
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
            <a href="{{ $checkUrl }}" class="text-primary-600 hover:underline dark:text-primary-400">Check completion</a>
            <a href="{{ $historyUrl }}" class="text-primary-600 hover:underline dark:text-primary-400">History</a>
            @if (filled($sendReportUrl))
                <a href="{{ $sendReportUrl }}" class="text-primary-600 hover:underline dark:text-primary-400">Send report</a>
            @endif
        </div>
    </div>

    @if (filled($windowNote))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            {{ $windowNote }}
        </div>
    @endif

    <div
        wire:loading
        wire:target="sendCombinedForBatch,sendCombined"
        class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
    >
        Messages are going to parents. Please wait. Do not click again.
    </div>

    <div class="flex min-w-0 flex-wrap gap-2 text-xs font-medium">
        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Waiting {{ $waiting }} · check</span>
        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">Ready {{ $ready }} · send</span>
        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Sent {{ $sent }} · parents have it</span>
        @if ($left > 0)
            <span class="rounded-full bg-rose-100 px-2.5 py-1 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200">Not submitted {{ $left }}</span>
        @endif
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600 dark:bg-white/5 dark:text-gray-300">No homework {{ $empty }}</span>
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
                                $leftOut = collect($section['items'] ?? [])->filter(function (array $item): bool {
                                    return blank($item['status_key'] ?? null) && blank($item['closure_reason'] ?? null);
                                })->values();
                                $subjectCount = count($section['items'] ?? []);
                                $waitingCount = (int) $section['submitted'];
                                $sectionTitle = filled($section['section']) && $section['section'] !== '—'
                                    ? (string) $section['section']
                                    : (string) $section['class_label'];
                            @endphp
                            <div @class([
                                'px-4 py-3',
                                'bg-primary-50/40 dark:bg-primary-500/10' => $isOpen,
                                'border-l-4 border-rose-400' => $leftOut->isNotEmpty(),
                                'ring-2 ring-inset ring-rose-500' => $sendConfirmBatchId === $batchId,
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
                                                @if ($leftOut->isNotEmpty())
                                                    <span class="font-semibold text-rose-700 dark:text-rose-300">Not submitted: {{ $leftOut->map(fn (array $item): string => $item['subject'].(filled($item['teacher'] ?? null) ? ' · '.$item['teacher'] : ''))->join(', ') }}</span>
                                                @elseif (! $hasWaiting && ! $hasReady && ! $hasSent)
                                                    No homework yet
                                                @else
                                                    @if ($hasWaiting)
                                                        {{ (int) $section['submitted'] }} waiting
                                                    @endif
                                                    @if ($hasReady)
                                                        {{ $hasWaiting ? ' · ' : '' }}ready to send
                                                    @endif
                                                    @if ($hasSent)
                                                        {{ ($hasWaiting || $hasReady) ? ' · ' : '' }}sent
                                                    @endif
                                                @endif
                                            </span>
                                        </span>
                                    </button>
                                    <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
                                        @if ($hasWaiting)
                                            <span class="rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">
                                                {{ $waitingCount }} to check
                                            </span>
                                        @elseif (! $hasReady && ! $hasSent)
                                            <span class="px-1 text-xs font-medium text-gray-400 dark:text-gray-500">
                                                @if ($subjectCount < 1)
                                                    No subjects
                                                @else
                                                    {{ $subjectCount }} {{ $subjectCount === 1 ? 'subject' : 'subjects' }} · none yet
                                                @endif
                                            </span>
                                        @endif
                                        @if ($canSend && $hasReady)
                                            <button
                                                type="button"
                                                wire:click="sendCombinedForBatch({{ $batchId }})"
                                                wire:loading.attr="disabled"
                                                wire:target="sendCombinedForBatch,sendCombined"
                                                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:cursor-wait disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="sendCombinedForBatch,sendCombined">Send to parents</span>
                                                <span wire:loading wire:target="sendCombinedForBatch,sendCombined">Sending… please wait</span>
                                            </button>
                                        @elseif ($canSend && $hasSent && $leftOut->isEmpty())
                                            <button
                                                type="button"
                                                wire:click="askDuplicateSend({{ $batchId }})"
                                                wire:loading.attr="disabled"
                                                wire:target="askDuplicateSend,confirmDuplicateSend,sendCombinedForBatch,sendCombined"
                                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:bg-transparent dark:text-gray-200 dark:hover:bg-white/5 disabled:cursor-wait disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="askDuplicateSend,confirmDuplicateSend,sendCombinedForBatch,sendCombined">Resend</span>
                                                <span wire:loading wire:target="askDuplicateSend,confirmDuplicateSend,sendCombinedForBatch,sendCombined">Sending… please wait</span>
                                            </button>
                                        @elseif ($canSend && $hasSent && $leftOut->isNotEmpty())
                                            <button
                                                type="button"
                                                wire:click="sendCombinedForBatch({{ $batchId }})"
                                                wire:loading.attr="disabled"
                                                wire:target="sendCombinedForBatch,sendCombined"
                                                class="rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/40 dark:bg-transparent dark:text-rose-200 dark:hover:bg-rose-500/10 disabled:cursor-wait disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="sendCombinedForBatch,sendCombined">Send to parents</span>
                                                <span wire:loading wire:target="sendCombinedForBatch,sendCombined">Sending… please wait</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @if ($isOpen)
                                    @if (($section['items'] ?? []) === [])
                                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No subjects on this class yet.</p>
                                    @else
                                        <ul class="mt-3 space-y-2">
                                            @foreach ($section['items'] as $item)
                                                @if (filled($item['status_key']))
                                                    <li
                                                        x-data="{ who: false }"
                                                        @class([
                                                        'rounded-xl border bg-white px-3 py-3 dark:bg-gray-900/60',
                                                        'border-amber-200 dark:border-amber-500/20' => $item['status_key'] === 'submitted',
                                                        'border-sky-200 dark:border-sky-500/20' => $item['status_key'] === 'approved',
                                                        'border-emerald-200 dark:border-emerald-500/20' => $item['status_key'] === 'sent',
                                                        'border-gray-200 dark:border-white/10' => ! in_array($item['status_key'], ['submitted', 'approved', 'sent'], true),
                                                    ])>
                                                        <div class="flex min-w-0 items-start justify-between gap-3">
                                                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                                                @if (filled($item['public_url']))
                                                                    <a
                                                                        href="{{ $item['public_url'] }}"
                                                                        target="_blank"
                                                                        rel="noopener"
                                                                        class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-300"
                                                                    >
                                                                        {{ $item['subject'] }}
                                                                    </a>
                                                                @else
                                                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['subject'] }}</p>
                                                                @endif
                                                                @if (filled($item['teacher']))
                                                                    <span class="inline-flex rounded-md bg-sky-50 px-1.5 py-0.5 text-xs font-semibold text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">{{ $item['teacher'] }}</span>
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
                                                                @if ($item['status_key'] === 'sent' && (int) ($item['link_total'] ?? 0) > 0)
                                                                    <p class="mt-1">
                                                                        <button
                                                                            type="button"
                                                                            x-on:click="who = ! who"
                                                                            class="text-xs font-semibold text-gray-700 hover:underline dark:text-gray-200"
                                                                        >
                                                                            Opened {{ (int) $item['link_opened'] }} / {{ (int) $item['link_total'] }}
                                                                        </button>
                                                                    </p>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        @if (filled($item['title']))
                                                            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['title'] }}</p>
                                                        @endif
                                                        @if (filled($item['description']))
                                                            <p class="mt-1 line-clamp-3 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $item['description'] }}</p>
                                                        @endif
                                                        @if ($item['has_file'])
                                                            <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">File attached</p>
                                                        @endif
                                                        @if (filled($item['submitted_by']) && $item['submitted_by'] !== $item['teacher'])
                                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Added by {{ $item['submitted_by'] }}</p>
                                                        @endif

                                                        <div class="mt-2.5 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                                                            @if (filled($item['public_url']))
                                                                <a
                                                                    href="{{ $item['public_url'] }}"
                                                                    target="_blank"
                                                                    rel="noopener"
                                                                    class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                                                >
                                                                    Open homework
                                                                </a>
                                                            @endif
                                                            @if ($canEnter && in_array($item['status_key'], ['submitted', 'approved'], true) && $item['assignment_id'])
                                                                <button
                                                                    type="button"
                                                                    wire:click="startAdd({{ (int) $batchId }}, {{ (int) $item['course_subject_id'] }}, {{ (int) $item['assignment_id'] }})"
                                                                    class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300"
                                                                >
                                                                    Edit
                                                                </button>
                                                            @endif
                                                            @if ($item['status_key'] === 'submitted' && $item['assignment_id'])
                                                                <button
                                                                    type="button"
                                                                    wire:click="approve({{ (int) $item['assignment_id'] }})"
                                                                    class="text-xs font-semibold text-sky-700 hover:underline dark:text-sky-300"
                                                                >
                                                                    Approve
                                                                </button>
                                                            @endif
                                                            @if ($item['assignment_id'] && $item['status_key'] !== 'sent')
                                                                <button
                                                                    type="button"
                                                                    wire:click="remove({{ (int) $item['assignment_id'] }})"
                                                                    wire:confirm="Remove this subject's homework for the day?"
                                                                    class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-400"
                                                                >
                                                                    Remove
                                                                </button>
                                                            @endif
                                                        </div>

                                                        @if ($item['status_key'] === 'sent' && (int) ($item['link_total'] ?? 0) > 0)
                                                            <div x-show="who" x-cloak class="mt-3 grid gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 text-xs dark:border-white/10 dark:bg-white/5 sm:grid-cols-2">
                                                                <div>
                                                                    <p class="font-semibold text-gray-900 dark:text-gray-100">Opened</p>
                                                                    <ul class="mt-1 space-y-0.5 text-gray-600 dark:text-gray-300">
                                                                        @forelse ($item['link_opened_people'] ?? [] as $person)
                                                                            <li>
                                                                                {{ $person['name'] }}
                                                                                @if (filled($person['at'] ?? null))
                                                                                    <span class="text-gray-400">· {{ $person['at'] }}</span>
                                                                                @endif
                                                                            </li>
                                                                        @empty
                                                                            <li class="text-gray-400">None yet</li>
                                                                        @endforelse
                                                                    </ul>
                                                                </div>
                                                                <div>
                                                                    <p class="font-semibold text-gray-900 dark:text-gray-100">Not opened</p>
                                                                    <ul class="mt-1 space-y-0.5 text-gray-600 dark:text-gray-300">
                                                                        @forelse ($item['link_not_opened_people'] ?? [] as $name)
                                                                            <li>{{ $name }}</li>
                                                                        @empty
                                                                            <li class="text-gray-400">Everyone opened</li>
                                                                        @endforelse
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </li>
                                                @else
                                                    <li class="flex min-w-0 items-center justify-between gap-3 rounded-lg px-3 py-1.5 text-xs">
                                                        <span class="text-gray-400">
                                                            {{ $item['subject'] }}
                                                            @if (filled($item['teacher']))
                                                                <span class="ml-1 inline-flex rounded-md bg-sky-50 px-1.5 py-0.5 font-semibold text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">{{ $item['teacher'] }}</span>
                                                            @endif
                                                            @if (filled($item['closure_label'] ?? null))
                                                                <span class="ml-1 font-semibold text-rose-700 dark:text-rose-300">{{ $item['closure_label'] }}</span>
                                                            @endif
                                                        </span>
                                                        @if ($canEnter && blank($item['closure_reason'] ?? null))
                                                            <button
                                                                type="button"
                                                                wire:click="startAdd({{ $batchId }}, {{ (int) $item['course_subject_id'] }})"
                                                                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                                                            >
                                                                Add homework
                                                            </button>
                                                        @endif
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

    @if ($sendConfirmBatchId > 0 && $missingSubjects !== [])
        @php
            $confirmCourse = '';
            $confirmSection = '';

            foreach ($desk['groups'] ?? [] as $courseRow) {
                foreach ($courseRow['sections'] ?? [] as $sectionRow) {
                    if ((int) ($sectionRow['batch_id'] ?? 0) !== $sendConfirmBatchId) {
                        continue;
                    }

                    $confirmCourse = (string) ($courseRow['course_name'] ?? '');
                    $confirmSection = filled($sectionRow['section'] ?? null) && $sectionRow['section'] !== '—'
                        ? (string) $sectionRow['section']
                        : (string) ($sectionRow['class_label'] ?? '');
                    break 2;
                }
            }

            $readyToSend = collect($missingSubjects)->every(function (array $missing) use ($missingSubjectReasons): bool {
                $subjectId = $missing['course_subject_id'];

                return filled($missingSubjectReasons[$subjectId] ?? $missingSubjectReasons[(string) $subjectId] ?? null);
            });
        @endphp
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-gray-950/60 p-0 sm:items-center sm:p-6"
            wire:click.self="cancelClosedSend"
        >
            <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl dark:bg-gray-900 sm:rounded-2xl">
                <div class="border-b border-rose-100 bg-rose-50 px-4 py-4 dark:border-rose-500/20 dark:bg-rose-500/10 sm:px-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-200">{{ $confirmCourse }} · {{ $confirmSection }}</p>
                    <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">These subjects have no homework</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Choose one answer for each subject. After you send, that teacher cannot add homework for this day.</p>
                </div>
                <ul class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4 sm:px-5">
                    @foreach ($missingSubjects as $missing)
                        @php
                            $chosen = $missingSubjectReasons[$missing['course_subject_id']] ?? $missingSubjectReasons[(string) $missing['course_subject_id']] ?? null;
                        @endphp
                        <li @class([
                            'rounded-xl border px-3 py-3',
                            'border-emerald-300 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/10' => filled($chosen),
                            'border-rose-300 bg-rose-50/40 dark:border-rose-500/30 dark:bg-rose-500/10' => blank($chosen),
                        ])>
                            <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $missing['subject'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $missing['teacher'] }}</p>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button
                                    type="button"
                                    wire:click="setMissingReason({{ (int) $missing['course_subject_id'] }}, 'teacher_absent')"
                                    @class([
                                        'rounded-xl px-3 py-3 text-sm font-semibold',
                                        'bg-rose-600 text-white' => $chosen === 'teacher_absent',
                                        'border border-gray-300 bg-white text-gray-800 dark:border-white/15 dark:bg-gray-900 dark:text-gray-100' => $chosen !== 'teacher_absent',
                                    ])
                                >
                                    Teacher was absent
                                </button>
                                <button
                                    type="button"
                                    wire:click="setMissingReason({{ (int) $missing['course_subject_id'] }}, 'no_homework')"
                                    @class([
                                        'rounded-xl px-3 py-3 text-sm font-semibold',
                                        'bg-sky-600 text-white' => $chosen === 'no_homework',
                                        'border border-gray-300 bg-white text-gray-800 dark:border-white/15 dark:bg-gray-900 dark:text-gray-100' => $chosen !== 'no_homework',
                                    ])
                                >
                                    No homework today
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-gray-100 bg-white px-4 py-4 dark:border-white/10 dark:bg-gray-900 sm:px-5">
                    @if (! $readyToSend)
                        <p class="mb-3 text-sm font-semibold text-rose-700 dark:text-rose-300">Choose one answer for each subject. Then Send to parents will work.</p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="confirmClosedSend"
                            wire:loading.attr="disabled"
                            wire:target="confirmClosedSend,sendCombined"
                            @disabled(! $readyToSend)
                            @class([
                                'rounded-xl px-4 py-3 text-sm font-semibold',
                                'bg-primary-600 text-white hover:bg-primary-500' => $readyToSend,
                                'cursor-not-allowed bg-gray-300 text-gray-500 dark:bg-white/10 dark:text-gray-400' => ! $readyToSend,
                            ])
                        >
                            <span wire:loading.remove wire:target="confirmClosedSend,sendCombined">Send to parents</span>
                            <span wire:loading wire:target="confirmClosedSend,sendCombined">Sending… please wait</span>
                        </button>
                        <button
                            type="button"
                            wire:click="cancelClosedSend"
                            class="rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:bg-transparent dark:text-gray-200"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($duplicateSendBatchId > 0)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-gray-950/60 p-0 sm:items-center sm:p-6"
            wire:click.self="cancelDuplicateSend"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-t-2xl bg-white shadow-2xl dark:bg-gray-900 sm:rounded-2xl">
                <div class="border-b border-amber-100 bg-amber-50 px-4 py-4 dark:border-amber-500/20 dark:bg-amber-500/10 sm:px-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ $duplicateSendLabel }}</p>
                    <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">This homework was already sent</h3>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">Sending again is a duplicate. Parents may get the same homework twice. Do not send again unless a parent did not receive it.</p>
                </div>
                <div class="flex flex-wrap gap-2 px-4 py-4 sm:px-5">
                    <button
                        type="button"
                        wire:click="cancelDuplicateSend"
                        class="rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900"
                    >
                        Go back
                    </button>
                    <button
                        type="button"
                        wire:click="confirmDuplicateSend"
                        wire:loading.attr="disabled"
                        wire:target="confirmDuplicateSend,sendCombined"
                        class="rounded-xl border border-amber-400 bg-white px-4 py-3 text-sm font-semibold text-amber-800 hover:bg-amber-50 dark:border-amber-500/40 dark:bg-transparent dark:text-amber-200"
                    >
                        <span wire:loading.remove wire:target="confirmDuplicateSend,sendCombined">Send again anyway</span>
                        <span wire:loading wire:target="confirmDuplicateSend,sendCombined">Sending… please wait</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
