@php
    use App\Enums\BatchStaffRole;
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-3xl lg:pb-6">
    @if ($assignments === [])
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">No class assignments yet</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                When admin assigns you as a class lead or subject teacher on a batch/section, it appears here.
            </p>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($assignments as $row)
                @php
                    $batch = $row['batch'];
                    $role = $row['role'];
                    $subject = $row['course_subject'];
                    $isLead = $role === BatchStaffRole::LeadTeacher;
                @endphp
                <div class="rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.03] sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">
                                {{ $isLead ? 'Class / batch lead' : 'Subject teacher' }}
                            </p>
                            <p class="mt-1 text-base font-bold text-gray-950 dark:text-white">
                                {{ $batch?->name ?? '—' }}
                            </p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ $batch?->course?->name ?? '—' }}
                                @if ($batch?->academicSession)
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    {{ $batch->academicSession->name }}
                                @endif
                                @if ($batch?->section)
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    Section {{ $batch->section }}
                                @endif
                            </p>
                            @if (! $isLead && $subject)
                                <p class="mt-2 text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    Subject: {{ $subject->displayLabel() }}
                                </p>
                            @endif
                        </div>

                        @if ($isLead)
                            <span class="inline-flex shrink-0 rounded-md bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800 ring-1 ring-amber-500/20 dark:text-amber-300">
                                Lead
                            </span>
                        @else
                            <span class="inline-flex shrink-0 rounded-md bg-sky-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-sky-800 ring-1 ring-sky-500/20 dark:text-sky-300">
                                Subject
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
