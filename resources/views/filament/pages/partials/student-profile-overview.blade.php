@php
    use App\Enums\ProfilePhase;

    $phase = $profile['phase'];
    $timeline = collect($activityTimeline ?? []);
    $grouped = $timeline->groupBy('occurred_date');
@endphp

<div class="space-y-5">
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3.5 sm:px-6 dark:border-white/10">
            <h3 class="text-sm font-bold text-gray-950 dark:text-white">Activity timeline</h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Everything related to this student — newest first. Click a row to open the detail tab.
            </p>
        </div>

        @if (! ($activityTimelineLoaded ?? false))
            <p class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">Loading activity…</p>
        @elseif ($timeline->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">
                No activity recorded yet for this student.
            </p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($grouped as $date => $items)
                    <div>
                        <div class="bg-gray-50 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400 sm:px-6">
                            {{ $items->first()['occurred_date_label'] ?? $date }}
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($items as $item)
                                @php
                                    $tone = match ($item['type'] ?? '') {
                                        'call' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
                                        'visit' => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
                                        'case' => 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300',
                                        'fee' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
                                        'whatsapp' => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                                        'attendance' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-300',
                                        'homework' => 'bg-orange-100 text-orange-900 dark:bg-orange-500/15 dark:text-orange-300',
                                        'exam' => 'bg-fuchsia-100 text-fuchsia-900 dark:bg-fuchsia-500/15 dark:text-fuchsia-300',
                                        'certificate' => 'bg-teal-100 text-teal-800 dark:bg-teal-500/15 dark:text-teal-300',
                                        'document' => 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-gray-300',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
                                    };
                                    $clickable = filled($item['tab'] ?? null) && ($item['tab'] ?? '') !== 'overview';
                                @endphp
                                <li
                                    @if ($clickable)
                                        wire:click="openActivityTimelineTab(@js($item['tab']))"
                                        role="button"
                                        tabindex="0"
                                        class="cursor-pointer px-4 py-3.5 transition hover:bg-primary-50/60 sm:px-6 dark:hover:bg-white/5"
                                    @else
                                        class="px-4 py-3.5 sm:px-6"
                                    @endif
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold tracking-wide {{ $tone }}">
                                            {{ $item['category'] ?? 'EVENT' }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                                <p class="font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                                                <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $item['occurred_at_label'] }}
                                                    @if (filled($item['staff_name'] ?? null))
                                                        · {{ $item['staff_name'] }}
                                                    @endif
                                                </p>
                                            </div>
                                            @if (filled($item['summary'] ?? null))
                                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $item['summary'] }}</p>
                                            @endif
                                            @if (filled($item['detail'] ?? null))
                                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item['detail'] }}</p>
                                            @endif
                                            @if ($clickable)
                                                <p class="mt-1 text-[11px] font-medium text-primary-600 dark:text-primary-400">
                                                    View in {{ str_replace('_', ' ', (string) $item['tab']) }} →
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            @if ($activityTimelineHasMore ?? false)
                <div class="border-t border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                    <button
                        type="button"
                        wire:click="loadMoreActivityTimeline"
                        class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        Load more activity
                    </button>
                </div>
            @endif
        @endif
    </div>

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
                        @foreach ($enquiries->take(5) as $enquiry)
                            <div class="px-4 py-3 sm:px-6">
                                <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $enquiry->course?->name ?? 'Course not selected' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $enquiry->enquiry_number }}
                                    · {{ $enquiry->latest_visit_status?->label() ?? '—' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
