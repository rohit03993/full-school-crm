@php
    $dossier = $profile['dossier'];
    $enrollment = $dossier['enrollment'];
    $course = $enrollment->course;
    $batch = $dossier['batch'] ?? null;
    $fees = $dossier['fees'];
    $photo = $dossier['photo'];
    $items = $profile['items'];
    $columnCount = min(count($items), 5);
    $netFee = $fees ? (float) $fees->net_fee : null;
    $tuitionPending = $fees ? (float) $fees->pending_amount : null;
    $miscPending = $fees ? (float) $fees->separateMiscChargesPendingTotal() : 0;
    $totalDue = $fees ? (float) $fees->totalCollectiblePending() : null;
@endphp

<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="bg-gradient-to-br from-primary-500/6 via-white to-emerald-500/5 px-4 py-4 dark:from-primary-500/10 dark:via-gray-900 sm:px-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-5">
            <div class="flex shrink-0 flex-col items-center lg:items-start">
                @if ($photo && $photo->isImage())
                    <button
                        type="button"
                        class="js-media-preview-trigger group relative cursor-zoom-in overflow-hidden rounded-xl ring-2 ring-white shadow-md dark:ring-gray-800"
                        data-preview-url="{{ $photo->previewUrl() }}"
                        data-preview-title="{{ $record->name }} — photo"
                        data-preview-pdf="0"
                    >
                        <img
                            src="{{ $photo->previewUrl() }}"
                            alt="{{ $record->name }}"
                            class="h-28 w-[5.5rem] object-cover transition duration-300 group-hover:scale-105 sm:h-32 sm:w-24"
                        />
                    </button>
                @else
                    <div class="flex h-28 w-[5.5rem] flex-col items-center justify-center rounded-xl border border-dashed border-primary-200/80 bg-white/90 dark:border-primary-500/20 dark:bg-white/5 sm:h-32 sm:w-24">
                        <svg class="h-8 w-8 text-primary-300 dark:text-primary-500/50" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span class="mt-1 text-[10px] text-gray-400">No photo</span>
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                {{ $record->status->label() }}
                            </span>
                            <span class="font-mono text-[11px] font-semibold text-primary-600 dark:text-primary-400">{{ $enrollment->enrollment_number }}</span>
                        </div>
                        <h2 class="mt-1.5 truncate text-lg font-bold text-gray-950 sm:text-xl dark:text-white">{{ $record->name }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $course?->name ?? '—' }}@if ($course?->duration_label) · {{ $course->duration_label }}@endif</p>
                        @include('filament.pages.partials.student-calling-assignment-banner', [
                            'callingAssignment' => $profile['calling_assignment'] ?? null,
                        ])
                        @include('filament.pages.partials.student-meeting-assignment-banner', [
                            'meetingAssignment' => $profile['meeting_assignment'] ?? null,
                        ])
                    </div>

                    @if ($enrollment->hasIdCard())
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" wire:click="openIdCardPreview" class="inline-flex min-h-8 items-center rounded-lg bg-primary-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-primary-500">
                                View ID card
                            </button>
                            <a href="{{ $enrollment->idCardDownloadUrl() }}" class="inline-flex min-h-8 items-center rounded-lg bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">
                                Download
                            </a>
                            @if (auth()->user()?->hasRole(\App\Enums\RoleName::SuperAdmin->value))
                                <button type="button" wire:click="regenerateIdCard" wire:confirm="Regenerate ID card with the latest student photo and details?" class="inline-flex min-h-8 items-center rounded-lg bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
                                    Regenerate
                                </button>
                            @endif
                        </div>
                    @elseif ($fees && (float) $fees->paid_amount > 0)
                        <x-filament::button wire:click="generateIdCard" size="xs" color="gray" icon="heroicon-o-identification">
                            Generate ID card
                        </x-filament::button>
                    @endif
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    @if ($fees)
                        <div class="rounded-lg border border-gray-100 bg-white/90 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Net tuition</p>
                            <p class="text-base font-bold text-gray-950 dark:text-white">₹{{ number_format($netFee, 0) }}</p>
                            <p class="text-[10px] text-gray-500">Paid ₹{{ number_format((float) $fees->paid_amount, 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-white/90 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">Balance due</p>
                            <p class="text-base font-bold text-amber-700 dark:text-amber-400">₹{{ number_format($totalDue, 0) }}</p>
                            <p class="text-[10px] text-gray-500">
                                Tuition ₹{{ number_format($tuitionPending, 0) }}
                                @if ($miscPending > 0) + misc ₹{{ number_format($miscPending, 0) }}@endif
                            </p>
                        </div>
                    @else
                        <div class="rounded-lg border border-gray-100 bg-white/90 px-3 py-2 dark:border-white/10 dark:bg-white/5 sm:col-span-2">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Course fee</p>
                            <p class="text-base font-bold text-gray-950 dark:text-white">{{ $course?->formatted_fee ?? '—' }}</p>
                        </div>
                    @endif
                    <div class="rounded-lg border border-gray-100 bg-white/90 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Batch</p>
                        <p class="truncate text-base font-bold text-gray-950 dark:text-white">{{ $batch?->name ?? 'Not assigned' }}</p>
                        @if ($batch?->trainer)
                            <p class="truncate text-[10px] text-gray-500">{{ $batch->trainer->name }}</p>
                        @endif
                    </div>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs sm:grid-cols-3 lg:grid-cols-5">
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">DOB</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $record->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Father</dt>
                        <dd class="truncate font-medium text-gray-950 dark:text-white">{{ $record->father_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Gender</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $record->gender?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Mobile</dt>
                        <dd class="flex flex-wrap items-center gap-1 font-medium text-gray-950 dark:text-white">
                            <span>{{ $record->mobile }}</span>
                            @include('filament.pages.partials.student-call-button', ['record' => $record])
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Category</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $record->category?->label() ?? '—' }}</dd>
                    </div>
                </dl>

                @include('filament.pages.partials.student-last-call-summary', ['record' => $record])
            </div>
        </div>
    </div>

    <div @class([
        'grid gap-1.5 border-t border-gray-100 bg-gray-50/60 px-3 py-2 dark:border-white/10 dark:bg-white/[0.02] sm:px-4',
        'grid-cols-2 sm:grid-cols-3' => $columnCount === 3,
        'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5' => $columnCount >= 5,
    ])>
        @foreach ($items as $counter)
            <div class="rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-gray-200/70 dark:bg-white/5 dark:ring-white/10">
                <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-500">{{ $counter['label'] }}</p>
                <p class="mt-0.5 truncate text-xs font-bold text-gray-950 dark:text-white">{{ $counter['value'] }}</p>
            </div>
        @endforeach
    </div>
</div>
