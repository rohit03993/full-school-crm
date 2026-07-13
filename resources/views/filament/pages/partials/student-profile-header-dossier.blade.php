@php
    $dossier = $profile['dossier'];
    $enrollment = $dossier['enrollment'];
    $course = $enrollment->course;
    $batch = $dossier['batch'] ?? null;
    $fees = $dossier['fees'];
    $photo = $dossier['photo'];
    $items = $profile['items'];
    $tuitionPaid = $fees ? (float) $fees->paid_amount : 0;

    $hasDetailChips = $record->date_of_birth || $record->father_name || $record->gender || $record->category;
    $hasMobileDetails = $hasDetailChips || $record->last_call_at || ((int) $record->total_calls === 0 && filled($record->mobile));

    $statIcons = [
        'Batch' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
        'Attendance' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
        'Exam' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
        'Mock Test' => 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V8.25a2.25 2.25 0 0 0-2.25-2.25h-5.379',
        'Training & Job' => 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0',
        'Event' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z',
    ];
@endphp

<div class="fi-student-profile-dossier overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    {{-- Identity row — compact side-by-side on mobile --}}
    <div class="relative overflow-hidden border-b border-gray-100 dark:border-white/10">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary-500/[0.07] via-transparent to-emerald-500/[0.05] dark:from-primary-500/10 dark:to-emerald-500/5"></div>

        <div class="relative flex flex-row items-start gap-3 p-3 sm:gap-4 sm:p-5 lg:gap-5">
            {{-- Photo --}}
            <div class="shrink-0">
                @if ($photo && $photo->isImage())
                    <button
                        type="button"
                        class="js-media-preview-trigger group relative cursor-zoom-in overflow-hidden rounded-xl shadow-md ring-2 ring-white dark:ring-gray-800 sm:rounded-2xl sm:shadow-lg"
                        data-preview-url="{{ $photo->previewUrl() }}"
                        data-preview-title="{{ $record->name }} — photo"
                        data-preview-pdf="0"
                    >
                        <img
                            src="{{ $photo->previewUrl() }}"
                            alt="{{ $record->name }}"
                            class="h-16 w-14 object-cover transition duration-300 group-hover:scale-105 sm:h-28 sm:w-[5.5rem]"
                        />
                    </button>
                @else
                    <div class="flex h-16 w-14 flex-col items-center justify-center rounded-xl border border-dashed border-primary-200/80 bg-white/80 shadow-sm dark:border-primary-500/25 dark:bg-white/5 sm:h-28 sm:w-[5.5rem] sm:rounded-2xl">
                        <svg class="h-5 w-5 text-primary-300 dark:text-primary-500/50 sm:h-7 sm:w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span class="mt-0.5 text-[9px] text-gray-400 sm:mt-1 sm:text-[10px]">No photo</span>
                    </div>
                @endif
            </div>

            {{-- Name & meta --}}
            <div class="min-w-0 flex-1 text-left">
                <div class="flex flex-wrap items-start justify-between gap-x-2 gap-y-1.5">
                    <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-500/20 dark:text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            {{ $record->status->label() }}
                        </span>
                        <span class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300 sm:px-2 sm:text-[11px]">
                            {{ $enrollment->enrollment_number }}
                        </span>
                    </div>

                    {{-- ID card actions (fee details live on the Fees tab) --}}
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1">
                        @if ($enrollment->hasIdCard())
                            <button type="button" wire:click="openIdCardPreview" class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-2 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-primary-500 sm:gap-1.5 sm:px-3 sm:py-1.5 sm:text-xs">
                                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                                ID card
                            </button>
                            <a href="{{ $enrollment->idCardDownloadUrl() }}" class="inline-flex items-center rounded-lg bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15 sm:px-2.5 sm:py-1.5 sm:text-xs">
                                Download
                            </a>
                            @if (auth()->user()?->hasRole(\App\Enums\RoleName::SuperAdmin->value))
                                <button type="button" wire:click="regenerateIdCard" wire:confirm="Regenerate ID card with the latest student photo and details?" class="inline-flex items-center rounded-lg px-2 py-1 text-[11px] font-medium text-amber-700 transition hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-500/10 sm:px-2.5 sm:py-1.5 sm:text-xs">
                                    Regenerate
                                </button>
                            @endif
                        @elseif ($fees && $tuitionPaid > 0)
                            <x-filament::button wire:click="generateIdCard" size="xs" color="gray" icon="heroicon-o-identification">
                                Generate ID card
                            </x-filament::button>
                        @endif
                    </div>
                </div>

                <h2 class="mt-1 truncate text-lg font-bold tracking-tight text-gray-950 sm:mt-2 sm:text-2xl dark:text-white">{{ $record->name }}</h2>
                <p class="mt-0.5 truncate text-xs text-gray-600 sm:text-sm dark:text-gray-400">
                    {{ $course?->name ?? '—' }}@if ($course?->duration_label)<span class="text-gray-400"> · </span>{{ $course->duration_label }}@endif
                </p>

                {{-- Mobile: contact + call inline (desktop uses Contact card below) --}}
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 sm:hidden">
                    <span class="text-sm font-bold text-gray-950 dark:text-white">{{ $record->mobile }}</span>
                </div>

                <div class="fi-student-profile-dossier-banners">
                    @include('filament.pages.partials.student-calling-assignment-banner', [
                        'callingAssignment' => $profile['calling_assignment'] ?? null,
                    ])
                    @include('filament.pages.partials.student-meeting-assignment-banner', [
                        'meetingAssignment' => $profile['meeting_assignment'] ?? null,
                    ])
                </div>

                {{-- Quick metrics — desktop/tablet only (batch also in stats strip; contact on mobile above) --}}
                <div class="mt-3 hidden gap-2 sm:grid sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-100 bg-white/80 p-3 dark:border-white/10 dark:bg-white/[0.03]">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Batch</p>
                        <p class="mt-0.5 truncate text-lg font-bold text-gray-950 dark:text-white">{{ $batch?->name ?? 'Not assigned' }}</p>
                        @if ($batch?->trainer)
                            <p class="truncate text-[10px] text-gray-500">{{ $batch->trainer->name }}</p>
                        @endif
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-white/80 p-3 dark:border-white/10 dark:bg-white/[0.03]">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Contact</p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="text-sm font-bold text-gray-950 dark:text-white">{{ $record->mobile }}</span>
                            @include('filament.pages.partials.student-call-button', ['record' => $record])
                        </div>
                    </div>
                </div>

                {{-- Detail chips — always visible on sm+ --}}
                @if ($hasDetailChips)
                    <div class="mt-2 hidden flex-wrap items-center gap-1.5 sm:flex">
                        @if ($record->date_of_birth)
                            <span class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                <span class="font-medium text-gray-500">DOB</span> {{ $record->date_of_birth->format('d M Y') }}
                            </span>
                        @endif
                        @if ($record->father_name)
                            <span class="inline-flex max-w-[12rem] items-center gap-1 truncate rounded-lg bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                <span class="font-medium text-gray-500">Father</span> {{ $record->father_name }}
                            </span>
                        @endif
                        @if ($record->gender)
                            <span class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                {{ $record->gender->label() }}
                            </span>
                        @endif
                        @if ($record->category)
                            <span class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                {{ $record->category->label() }}
                            </span>
                        @endif
                    </div>
                @endif

                {{-- Mobile: collapsible secondary details --}}
                @if ($hasMobileDetails)
                    <details class="fi-student-profile-details-mobile mt-2 sm:hidden">
                        <summary class="touch-manipulation text-xs font-semibold text-primary-600 dark:text-primary-400">
                            More details
                        </summary>
                        <div class="mt-2 space-y-2">
                            @if ($hasDetailChips)
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($record->date_of_birth)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            <span class="font-medium text-gray-500">DOB</span> {{ $record->date_of_birth->format('d M Y') }}
                                        </span>
                                    @endif
                                    @if ($record->father_name)
                                        <span class="inline-flex max-w-full items-center gap-1 truncate rounded-lg bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            <span class="font-medium text-gray-500">Father</span> {{ $record->father_name }}
                                        </span>
                                    @endif
                                    @if ($record->gender)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            {{ $record->gender->label() }}
                                        </span>
                                    @endif
                                    @if ($record->category)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            {{ $record->category->label() }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            @include('filament.pages.partials.student-last-call-summary', ['record' => $record, 'compact' => true])
                        </div>
                    </details>
                @endif

                <div class="hidden sm:block">
                    @include('filament.pages.partials.student-last-call-summary', ['record' => $record])
                </div>
            </div>
        </div>
    </div>

    {{-- Activity stats — 2-column grid on mobile (no horizontal scroll) --}}
    <div class="bg-gray-50/80 px-3 py-2 dark:bg-white/[0.02] sm:px-4 sm:py-2.5">
        <div class="fi-student-profile-dossier-stats grid grid-cols-2 gap-1.5 sm:gap-2">
            @foreach ($items as $counter)
                @php
                    $iconPath = $statIcons[$counter['label']] ?? 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z';
                    $isHighlight = in_array($counter['label'], ['Attendance'], true) && str_contains((string) $counter['value'], '%');
                @endphp
                <div @class([
                    'flex items-center gap-2 rounded-lg bg-white px-2 py-2 shadow-sm ring-1 ring-gray-200/70 dark:bg-gray-900 dark:ring-white/10 sm:gap-2.5 sm:rounded-xl sm:px-3 sm:py-2.5',
                    'ring-emerald-200/80 dark:ring-emerald-500/20' => $isHighlight && (int) filter_var($counter['value'], FILTER_SANITIZE_NUMBER_INT) >= 75,
                ])>
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-primary-500/10 text-primary-600 sm:h-8 sm:w-8 sm:rounded-lg dark:text-primary-400">
                        <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-[9px] font-semibold uppercase tracking-wide text-gray-500 sm:text-[10px]">{{ $counter['label'] }}</p>
                        <p class="truncate text-xs font-bold text-gray-950 sm:text-sm dark:text-white">{{ $counter['value'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
