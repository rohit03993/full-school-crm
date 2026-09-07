@php
    $dossier = $profile['dossier'];
    $enrollment = $dossier['enrollment'];
    $admission = $dossier['admission'];
    $leadSources = $profile['lead_sources'];
    $batchName = $record->activeBatchStudent?->batch?->name;
@endphp

<div class="space-y-4 lg:space-y-5">
    @include('filament.pages.partials.student-open-cases-banner', ['profile' => $profile])

    <div class="grid gap-4 lg:gap-5">
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3.5 sm:px-6 dark:border-white/10">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Enrollment</h3>
            </div>
            <dl class="divide-y divide-gray-100 dark:divide-white/10">
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ \App\Support\StudentLabels::rollNumberLabel() }}</dt>
                    <dd class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $enrollment->enrollment_number }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Course</dt>
                    <dd class="text-right text-sm font-semibold text-gray-950 dark:text-white">{{ $enrollment->course?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Enrolled on</dt>
                    <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $enrollment->enrolled_at?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Batch</dt>
                    <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $batchName ?? 'Not assigned' }}</dd>
                </div>
                @if ($admission)
                    <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Admission no.</dt>
                        <dd class="font-mono text-sm text-gray-700 dark:text-gray-300">{{ $admission->admission_number }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    @if (($leadSources['website_count'] ?? 0) > 0 || ($leadSources['walk_in_count'] ?? 0) > 0 || ($leadSources['direct_admission_count'] ?? 0) > 0)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3.5 sm:px-6 dark:border-white/10">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Lead history</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $leadSources['headline'] }} · {{ $leadSources['detail'] }}</p>
            </div>
            <div class="flex flex-wrap gap-2 px-4 py-4 sm:px-6">
                @include('filament.pages.partials.lead-source-badges', ['leadSources' => $leadSources])
                @include('filament.pages.partials.meeting-for-badges', ['leadSources' => $leadSources])
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3.5 sm:px-6 dark:border-white/10">
            <div>
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">All enquiries</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $enquiries->count() }} on record</p>
            </div>
        </div>
        @if ($enquiries->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">No enquiries on record.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($enquiries as $enquiry)
                    <div class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $enquiry->course?->name ?? 'Course not selected' }}</p>
                            <p class="mt-0.5 font-mono text-xs text-primary-600 dark:text-primary-400">{{ $enquiry->enquiry_number }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $enquiry->lead_source?->label() }} · {{ $enquiry->created_at?->format('d M Y') }}
                            </p>
                        </div>
                        <span class="inline-flex w-fit shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-gray-600 dark:bg-white/10 dark:text-gray-300">
                            {{ $enquiry->latest_visit_status?->label() ?? '—' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
