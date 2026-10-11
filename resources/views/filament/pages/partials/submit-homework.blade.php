@php
    $desk = $desk ?? ['counts' => ['missing' => 0, 'submitted' => 0, 'approved' => 0, 'sent' => 0], 'groups' => []];
    $counts = $desk['counts'] ?? ['missing' => 0, 'submitted' => 0, 'approved' => 0, 'sent' => 0];
    $dateLabel = $dateLabel ?? '';
    $isToday = $isToday ?? false;
    $canEnter = (bool) ($canEnter ?? false);
    $windowNote = $windowNote ?? null;
    $checkBaseUrl = $checkBaseUrl ?? '#';
    $checkDate = $checkDate ?? now()->toDateString();
@endphp

<div class="space-y-3">
    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ $dateLabel }}
        @if ($isToday)
            · today
        @endif
    </p>

    @if (filled($windowNote))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            {{ $windowNote }}
        </div>
    @endif

    <div class="flex min-w-0 flex-wrap gap-2 text-xs font-medium">
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700 dark:bg-white/5 dark:text-gray-300">To add {{ (int) ($counts['missing'] ?? 0) }}</span>
        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Waiting for admin {{ (int) ($counts['submitted'] ?? 0) }}</span>
        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">Approved by admin {{ (int) ($counts['approved'] ?? 0) }}</span>
        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Sent to parents {{ (int) ($counts['sent'] ?? 0) }}</span>
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
                                        <li
                                            x-data="{ who: false }"
                                            @class([
                                                'rounded-xl border px-3 py-3 text-sm',
                                                'border-amber-200 bg-amber-50/40 dark:border-amber-500/20 dark:bg-amber-500/10' => ($subject['status_key'] ?? null) === 'submitted',
                                                'border-sky-200 bg-sky-50/50 dark:border-sky-500/20 dark:bg-sky-500/10' => ($subject['status_key'] ?? null) === 'approved',
                                                'border-emerald-200 bg-emerald-50/50 dark:border-emerald-500/20 dark:bg-emerald-500/10' => ($subject['status_key'] ?? null) === 'sent',
                                                'border-rose-200 bg-rose-50/40 dark:border-rose-500/20 dark:bg-rose-500/10' => ($subject['closed'] ?? false) && blank($subject['status_key'] ?? null),
                                                'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5' => blank($subject['status_key'] ?? null) && ! ($subject['closed'] ?? false),
                                            ])>
                                            <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $subject['subject'] }}</p>
                                                @if ($subject['closed'] ?? false)
                                                    <p class="mt-1 text-xs font-semibold text-rose-700 dark:text-rose-300">{{ $subject['closure_label'] ?? 'Closed for today' }}. You cannot add homework for this day.</p>
                                                    @if (filled($subject['closure_note'] ?? null))
                                                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $subject['closure_note'] }}</p>
                                                    @endif
                                                @elseif ($subject['status_key'])
                                                    <p @class([
                                                        'mt-1 text-xs font-semibold',
                                                        'text-amber-800 dark:text-amber-200' => $subject['status_key'] === 'submitted',
                                                        'text-sky-800 dark:text-sky-200' => $subject['status_key'] === 'approved',
                                                        'text-emerald-800 dark:text-emerald-200' => $subject['status_key'] === 'sent',
                                                    ])>{{ $subject['status_line'] ?? $subject['status'] }}</p>
                                                    @if (filled($subject['title'] ?? null))
                                                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $subject['title'] }}</p>
                                                    @endif
                                                    @if (filled($subject['description'] ?? null))
                                                        <p class="mt-0.5 line-clamp-2 text-xs text-gray-600 dark:text-gray-300">{{ $subject['description'] }}</p>
                                                    @endif
                                                    @if ($subject['locked'] ?? false)
                                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">You cannot change this.</p>
                                                    @endif
                                                    @if ($subject['status_key'] === 'sent' && (int) ($subject['link_total'] ?? 0) > 0)
                                                        <p class="mt-1">
                                                            <button
                                                                type="button"
                                                                x-on:click="who = ! who"
                                                                class="text-xs font-semibold text-gray-700 hover:underline dark:text-gray-200"
                                                            >
                                                                Opened {{ (int) $subject['link_opened'] }} / {{ (int) $subject['link_total'] }}
                                                            </button>
                                                        </p>
                                                    @endif
                                                @else
                                                    <p class="mt-0.5 text-xs text-gray-400">No homework yet</p>
                                                @endif
                                            </div>
                                            <div class="flex min-w-0 flex-wrap gap-2">
                                                @if ($canEnter && ! ($subject['closed'] ?? false) && ($subject['assignment_id'] === null || $subject['can_remove']))
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
                                                @if ($subject['assignment_id'])
                                                    <a
                                                        href="{{ $checkBaseUrl }}?{{ http_build_query(['batch_id' => (int) $section['batch_id'], 'course_subject_id' => (int) $subject['course_subject_id'], 'check_date' => $checkDate]) }}"
                                                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                                    >
                                                        Check completion
                                                    </a>
                                                @endif
                                            </div>
                                            </div>
                                            @if ($subject['status_key'] === 'sent' && (int) ($subject['link_total'] ?? 0) > 0)
                                                <div x-show="who" x-cloak class="mt-3 grid gap-3 rounded-lg border border-gray-100 bg-white px-3 py-2.5 text-xs dark:border-white/10 dark:bg-white/5 sm:grid-cols-2">
                                                    <div>
                                                        <p class="font-semibold text-gray-900 dark:text-gray-100">Opened</p>
                                                        <ul class="mt-1 space-y-0.5 text-gray-600 dark:text-gray-300">
                                                            @forelse ($subject['link_opened_people'] ?? [] as $person)
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
                                                            @forelse ($subject['link_not_opened_people'] ?? [] as $name)
                                                                <li>{{ $name }}</li>
                                                            @empty
                                                                <li class="text-gray-400">Everyone opened</li>
                                                            @endforelse
                                                        </ul>
                                                    </div>
                                                </div>
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
