@php
    use App\Enums\ProfilePhase;

    $phase = $profile['phase'];
    $recentVisits = $profile['recent_visits'];
@endphp

@if (($phase === ProfilePhase::Enrolled || $phase === ProfilePhase::ActiveStudent) && ($profile['dossier'] ?? null))
    @include('filament.pages.partials.student-profile-overview-dossier', [
        'record' => $record,
        'profile' => $profile,
        'enquiries' => $enquiries,
    ])
@elseif ($phase->isLeadStage())
    <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Contact</h3>
            </div>
            <dl class="grid gap-3 px-4 py-4 text-sm sm:grid-cols-2 sm:gap-4 sm:px-6 sm:pb-6">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->name }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Mobile</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->mobile }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Address</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ collect([$record->address, $record->city, $record->state, $record->pincode])->filter()->implode(', ') ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Enquiries</h3>
            </div>

            @if ($enquiries->isEmpty())
                <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">No enquiries yet.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($enquiries as $enquiry)
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $enquiry->course?->name ?? 'Course not selected' }}</p>
                                    <p class="mt-0.5 font-mono text-xs font-medium text-primary-600 dark:text-primary-400">{{ $enquiry->enquiry_number }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                    {{ $enquiry->latest_visit_status?->label() ?? '—' }}
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ring-1',
                                    'bg-emerald-500/15 text-emerald-700 ring-emerald-500/20 dark:text-emerald-400' => $enquiry->lead_source?->value === 'website',
                                    'bg-sky-500/15 text-sky-800 ring-sky-500/20 dark:text-sky-400' => $enquiry->lead_source?->value === 'walk_in',
                                    'bg-gray-500/10 text-gray-600 ring-gray-500/10 dark:text-gray-400' => ! in_array($enquiry->lead_source?->value, ['website', 'walk_in'], true),
                                ])>
                                    {{ $enquiry->lead_source?->label() ?? 'Lead' }}
                                </span>
                                @if ($enquiry->meeting_for)
                                    @include('filament.pages.partials.meeting-for-badge', [
                                        'value' => $enquiry->meeting_for,
                                        'size' => 'sm',
                                    ])
                                @endif
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $enquiry->created_at?->format('d M Y') }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 lg:col-span-2 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Recent Visits</h3>
            </div>

            @if ($recentVisits->isEmpty())
                <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">No visits recorded yet.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($recentVisits as $visit)
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $visit->visit_date?->format('d M Y') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $visit->enquiry?->course?->name ?? '—' }}
                                        · {{ $visit->staff?->name ?? 'Staff' }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                    {{ $visit->status?->label() ?? '—' }}
                                </span>
                            </div>
                            @if ($visit->discussion_summary)
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $visit->discussion_summary }}</p>
                            @endif
                            @if ($visit->next_follow_up_date)
                                <p class="mt-1 text-xs text-primary-600 dark:text-primary-400">
                                    Follow-up: {{ $visit->next_follow_up_date->format('d M Y') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@else
    <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Personal Details</h3>
            </div>
            <dl class="grid gap-3 px-4 py-4 text-sm sm:grid-cols-2 sm:gap-4 sm:px-6 sm:pb-6">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Father's Name</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->father_name ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Date of Birth</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->date_of_birth?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Gender</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->gender?->label() ?? '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Category</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->category?->label() ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Address</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ collect([$record->address, $record->city, $record->state, $record->pincode])->filter()->implode(', ') ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    @if ($record->activeEnrollment)
                        Student record
                    @else
                        Enquiries
                    @endif
                </h3>
            </div>

            @if ($record->activeEnrollment)
                <dl class="grid gap-3 px-4 py-4 text-sm sm:px-6 sm:pb-6">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ \App\Support\StudentLabels::rollNumberLabel() }}</dt><dd class="mt-0.5 font-mono font-medium text-primary-600 dark:text-primary-400">{{ $record->activeEnrollment->enrollment_number }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Course</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->activeEnrollment->course?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Enrolled On</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->activeEnrollment->enrolled_at?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Batch</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->hasActiveBatch() ? 'Assigned' : 'Not assigned yet' }}</dd></div>
                </dl>
            @elseif ($enquiries->isEmpty())
                <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">No enquiries yet.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($enquiries as $enquiry)
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $enquiry->course?->name }}</p>
                                    <p class="mt-0.5 font-mono text-xs font-medium text-primary-600 dark:text-primary-400">{{ $enquiry->enquiry_number }}</p>
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
@endif
