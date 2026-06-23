@php
    $dossier = $profile['dossier'];
    $enrollment = $dossier['enrollment'];
    $admission = $dossier['admission'];
    $fees = $dossier['fees'];
    $leadSources = $profile['lead_sources'];
    $batchName = $record->activeBatchStudent?->batch?->name;
@endphp

<div class="space-y-4 lg:space-y-5">
    @if ($admission)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3.5 sm:px-6 dark:border-white/10">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Academic record</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">From admission form</p>
            </div>
            <div class="grid gap-3 p-4 sm:grid-cols-3 sm:p-6">
                @foreach ([
                    ['label' => 'Class 10th', 'board' => $admission->tenth_board, 'pct' => $admission->tenth_percentage],
                    ['label' => 'Class 12th', 'board' => $admission->twelfth_board, 'pct' => $admission->twelfth_percentage],
                    ['label' => 'Graduation', 'board' => $admission->graduation, 'pct' => $admission->graduation_percentage],
                ] as $level)
                    <div class="rounded-xl border border-gray-100 bg-gradient-to-br from-gray-50 to-white p-4 dark:border-white/10 dark:from-white/5 dark:to-transparent">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">{{ $level['label'] }}</p>
                        <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ filled($level['board']) ? $level['board'] : '—' }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $level['pct'] !== null ? number_format((float) $level['pct'], 2).'%' : 'Percentage not recorded' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2 lg:gap-5">
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

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3.5 sm:px-6 dark:border-white/10">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Fee summary</h3>
            </div>
            @if ($fees)
                <div class="grid grid-cols-2 gap-3 p-4 sm:p-6">
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-white/5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Net fee</p>
                        <p class="mt-0.5 text-base font-bold text-gray-950 dark:text-white">₹{{ number_format((float) $fees->net_fee, 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-white/5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Discount</p>
                        <p class="mt-0.5 text-base font-bold text-gray-950 dark:text-white">₹{{ number_format((float) $fees->discount_amount, 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-500/10 px-3 py-2.5 ring-1 ring-emerald-500/15">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Paid</p>
                        <p class="mt-0.5 text-base font-bold text-emerald-700 dark:text-emerald-400">₹{{ number_format((float) $fees->paid_amount, 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-500/10 px-3 py-2.5 ring-1 ring-amber-500/15">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-400">Pending</p>
                        <p class="mt-0.5 text-base font-bold text-amber-800 dark:text-amber-400">₹{{ number_format((float) $fees->pending_amount, 2) }}</p>
                    </div>
                </div>
            @else
                <p class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">Fee structure not set.</p>
            @endif
        </div>
    </div>

    @if (($leadSources['website_count'] ?? 0) > 0 || ($leadSources['walk_in_count'] ?? 0) > 0)
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
