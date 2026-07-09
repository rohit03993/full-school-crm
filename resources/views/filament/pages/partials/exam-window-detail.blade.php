@php
    use App\Enums\ExamWindowStatus;
@endphp

@if (! $window)
    <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
        <p class="text-lg font-semibold text-gray-950 dark:text-white">Exam not found</p>
    </div>
@else
    @php
        $statusBadge = match ($window->status) {
            \App\Enums\ExamWindowStatus::Draft => 'bg-gray-500/15 text-gray-800 ring-gray-500/20 dark:text-gray-300',
            \App\Enums\ExamWindowStatus::Open => 'bg-sky-500/15 text-sky-800 ring-sky-500/20 dark:text-sky-300',
            \App\Enums\ExamWindowStatus::Submitted => 'bg-amber-500/15 text-amber-800 ring-amber-500/20 dark:text-amber-300',
            \App\Enums\ExamWindowStatus::Approved => 'bg-emerald-500/15 text-emerald-800 ring-emerald-500/20 dark:text-emerald-300',
        };
    @endphp
    <div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-4xl lg:pb-6">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ring-1 {{ $statusBadge }}">
                        {{ $window->status->label() }}
                    </span>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $batchLabel }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ $window->session_date?->format('d M Y') }}
                        @if ($window->activityType)
                            · {{ $window->activityType->name }}
                        @endif
                    </p>
                </div>
                <div class="text-right text-sm text-gray-600 dark:text-gray-400">
                    <p><strong class="text-gray-900 dark:text-white">{{ $progress['entered'] ?? 0 }}/{{ $progress['total'] ?? 0 }}</strong> subjects with marks</p>
                </div>
            </div>

            @if ($window->remarks)
                <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">
                    {{ $window->remarks }}
                </p>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                @if ($canOpen)
                    <button
                        type="button"
                        wire:click="openForTeachers"
                        class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                    >
                        Open for teachers
                    </button>
                @endif
                @if ($canSubmit)
                    <button
                        type="button"
                        wire:click="submitForApproval"
                        class="inline-flex items-center rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500"
                    >
                        Submit to admin
                    </button>
                @endif
                @if ($canApprove)
                    <button
                        type="button"
                        wire:click="approve"
                        class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500"
                    >
                        Approve exam
                    </button>
                @endif
                @if ($canPublish)
                    <a
                        href="{{ $reviewUrl }}"
                        class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                    >
                        Publish results
                    </a>
                @endif
            </div>

            @if ($window->status === ExamWindowStatus::Submitted && $window->submittedBy)
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Submitted by {{ $window->submittedBy->name }} · {{ $window->submitted_at?->format('d M Y, h:i A') }}
                </p>
            @endif
            @if ($window->status === ExamWindowStatus::Approved && $window->approvedBy)
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Approved by {{ $window->approvedBy->name }} · {{ $window->approved_at?->format('d M Y, h:i A') }}
                </p>
            @endif
        </div>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10 sm:px-5">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Subjects</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Each row opens mark entry for that subject.</p>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($progress['subjects'] ?? [] as $row)
                    @php
                        $windowSubject = $window->subjects->firstWhere('id', $row['id']);
                    @endphp
                    <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div>
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Max {{ $row['max_marks'] }} marks
                                @if ($row['entered'])
                                    · Entered by {{ $row['entered_by'] ?? 'staff' }}
                                @else
                                    · Pending
                                @endif
                            </p>
                        </div>
                        @if ($windowSubject && $canEnterMarks($windowSubject) && $row['activity_session_id'] && $window->status->allowsTeacherEntry())
                            <a
                                href="{{ $marksEntryUrl($row['activity_session_id']) }}"
                                class="inline-flex shrink-0 items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                            >
                                {{ $row['entered'] ? 'Edit marks' : 'Enter marks' }}
                            </a>
                        @elseif ($row['entered'])
                            <span class="inline-flex shrink-0 rounded-md bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Done</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
