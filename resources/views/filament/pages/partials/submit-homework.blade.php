@php
    $desk = $desk ?? ['counts' => ['missing' => 0, 'submitted' => 0, 'approved' => 0, 'sent' => 0], 'groups' => []];
    $counts = $desk['counts'] ?? ['missing' => 0, 'submitted' => 0, 'approved' => 0, 'sent' => 0];
    $dateLabel = $dateLabel ?? '';
    $isToday = $isToday ?? false;
    $selectedBatchId = (int) ($selectedBatchId ?? 0);
    $selectedSubjectId = (int) ($selectedSubjectId ?? 0);
    $ready = (bool) ($ready ?? false);
@endphp

<div class="space-y-3">
    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ $dateLabel }}
        @if ($isToday)
            · today
        @endif
    </p>

    <div class="flex min-w-0 flex-wrap gap-2 text-xs font-medium">
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700 dark:bg-white/5 dark:text-gray-300">To add: {{ (int) ($counts['missing'] ?? 0) }}</span>
        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Waiting: {{ (int) ($counts['submitted'] ?? 0) }}</span>
        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">Approved: {{ (int) ($counts['approved'] ?? 0) }}</span>
        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Sent: {{ (int) ($counts['sent'] ?? 0) }}</span>
    </div>

    @if (($desk['groups'] ?? []) === [])
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            You are not assigned to any class yet. Ask admin to assign you in Class &amp; Sections.
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
                            <div class="px-4 py-3">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $section['class_label'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Section {{ $section['section'] }}</p>
                                <ul class="mt-3 space-y-2">
                                    @foreach ($section['subjects'] as $subject)
                                        <li @class([
                                            'flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm',
                                            'bg-primary-50/70 dark:bg-primary-500/10' => $selectedBatchId === (int) $section['batch_id'] && $selectedSubjectId === (int) $subject['course_subject_id'],
                                            'bg-gray-50 dark:bg-white/5' => ! ($selectedBatchId === (int) $section['batch_id'] && $selectedSubjectId === (int) $subject['course_subject_id']),
                                        ])>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $subject['subject'] }}</p>
                                                @if ($subject['status_key'])
                                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                        <span @class([
                                                            'inline-flex rounded-full px-2 py-0.5 font-medium',
                                                            'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200' => $subject['status_key'] === 'submitted',
                                                            'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200' => $subject['status_key'] === 'approved',
                                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200' => $subject['status_key'] === 'sent',
                                                        ])>{{ $subject['status'] }}</span>
                                                        @if ($subject['title'])
                                                            <span class="ml-1">{{ $subject['title'] }}</span>
                                                        @endif
                                                    </p>
                                                @else
                                                    <p class="mt-0.5 text-xs text-gray-400">No homework yet</p>
                                                @endif
                                            </div>
                                            <div class="flex min-w-0 flex-wrap gap-2">
                                                @if ($subject['assignment_id'] === null || $subject['can_remove'])
                                                    <button
                                                        type="button"
                                                        wire:click="startAdd({{ (int) $section['batch_id'] }}, {{ (int) $subject['course_subject_id'] }})"
                                                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                                                    >
                                                        {{ $subject['assignment_id'] ? 'Update' : 'Add homework' }}
                                                    </button>
                                                @endif
                                                @if ($subject['can_remove'] && $subject['assignment_id'])
                                                    <button
                                                        type="button"
                                                        wire:click="deleteSubmission({{ (int) $subject['assignment_id'] }})"
                                                        wire:confirm="Remove this homework submission?"
                                                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-white/10 dark:hover:bg-rose-500/10"
                                                    >
                                                        Remove
                                                    </button>
                                                @endif
                                            </div>
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
