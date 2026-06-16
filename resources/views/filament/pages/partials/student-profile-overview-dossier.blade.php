@php
    $dossier = $profile['dossier'];
    $enrollment = $dossier['enrollment'];
    $admission = $dossier['admission'];
    $fees = $dossier['fees'];
    $leadSources = $profile['lead_sources'];
@endphp

<div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
  {{-- Academic record --}}
    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 lg:col-span-2">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Academic record</h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">From admission form</p>
        </div>
        @if ($admission)
            <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 lg:grid-cols-3 sm:px-6 sm:pb-6">
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <p class="text-xs font-bold text-primary-700 dark:text-primary-400">Class 10th</p>
                    <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">{{ $admission->tenth_board ?: '—' }}</p>
                    <p class="text-xs text-gray-500">{{ $admission->tenth_percentage !== null ? number_format((float) $admission->tenth_percentage, 2).'%' : '—' }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <p class="text-xs font-bold text-primary-700 dark:text-primary-400">Class 12th</p>
                    <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">{{ $admission->twelfth_board ?: '—' }}</p>
                    <p class="text-xs text-gray-500">{{ $admission->twelfth_percentage !== null ? number_format((float) $admission->twelfth_percentage, 2).'%' : '—' }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <p class="text-xs font-bold text-primary-700 dark:text-primary-400">Graduation</p>
                    <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">{{ $admission->graduation ?: '—' }}</p>
                    <p class="text-xs text-gray-500">{{ $admission->graduation_percentage !== null ? number_format((float) $admission->graduation_percentage, 2).'%' : 'N/A' }}</p>
                </div>
            </div>
        @else
            <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">No academic record on file.</p>
        @endif
    </div>

  {{-- Enrollment & fees --}}
    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Enrollment</h3>
        </div>
        <dl class="grid gap-3 px-4 py-4 text-sm sm:px-6 sm:pb-6">
            <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Enrollment no.</dt><dd class="mt-0.5 font-mono font-bold text-primary-600 dark:text-primary-400">{{ $enrollment->enrollment_number }}</dd></div>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Course</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $enrollment->course?->name ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Enrolled on</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $enrollment->enrolled_at?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Batch</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->hasActiveBatch() ? 'Assigned' : 'Not assigned yet' }}</dd></div>
            @if ($admission)
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Admission no.</dt><dd class="mt-0.5 font-mono text-sm text-gray-700 dark:text-gray-300">{{ $admission->admission_number }}</dd></div>
            @endif
        </dl>
    </div>

    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Fee summary</h3>
        </div>
        @if ($fees)
            <dl class="grid gap-3 px-4 py-4 text-sm sm:grid-cols-2 sm:px-6 sm:pb-6">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Net fee</dt><dd class="mt-0.5 font-bold text-primary-600 dark:text-primary-400">₹{{ number_format((float) $fees->net_fee, 2) }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Discount</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">₹{{ number_format((float) $fees->discount_amount, 2) }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Paid</dt><dd class="mt-0.5 font-semibold text-emerald-700 dark:text-emerald-400">₹{{ number_format((float) $fees->paid_amount, 2) }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending</dt><dd class="mt-0.5 font-semibold text-amber-700 dark:text-amber-400">₹{{ number_format((float) $fees->pending_amount, 2) }}</dd></div>
            </dl>
        @else
            <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">Fee structure not set.</p>
        @endif
    </div>

  {{-- Lead history (compact) --}}
    @if (($leadSources['website_count'] ?? 0) > 0 || ($leadSources['walk_in_count'] ?? 0) > 0)
        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 lg:col-span-2 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Lead history</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $leadSources['headline'] }} · {{ $leadSources['detail'] }}</p>
            </div>
            <div class="flex flex-wrap gap-2 px-4 py-4 sm:px-6 sm:pb-6">
                @include('filament.pages.partials.lead-source-badges', ['leadSources' => $leadSources])
                @include('filament.pages.partials.meeting-for-badges', ['leadSources' => $leadSources])
            </div>
        </div>
    @endif

  {{-- Enquiries list --}}
    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 lg:col-span-2 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">All enquiries</h3>
        </div>
        @if ($enquiries->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">No enquiries on record.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($enquiries as $enquiry)
                    <div class="px-4 py-4 sm:px-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $enquiry->course?->name ?? 'Course not selected' }}</p>
                                <p class="mt-0.5 font-mono text-xs text-primary-600 dark:text-primary-400">{{ $enquiry->enquiry_number }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                {{ $enquiry->latest_visit_status?->label() ?? '—' }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $enquiry->lead_source?->label() }} · {{ $enquiry->created_at?->format('d M Y') }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
