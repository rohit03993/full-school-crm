@php
    $dossier = $profile['dossier'];
    $enrollment = $dossier['enrollment'];
    $course = $enrollment->course;
    $batch = $dossier['batch'] ?? null;
    $fees = $dossier['fees'];
    $photo = $dossier['photo'];
    $items = $profile['items'];
    $columnCount = min(count($items), 5);
@endphp

<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="bg-gradient-to-br from-primary-500/8 via-white to-emerald-500/5 px-4 py-5 dark:from-primary-500/10 dark:via-gray-900 dark:to-emerald-500/5 sm:px-6 sm:py-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:gap-6">
            <div class="flex shrink-0 flex-col items-center lg:items-start">
                @if ($photo && $photo->isImage())
                    <button
                        type="button"
                        class="js-media-preview-trigger group relative cursor-zoom-in overflow-hidden rounded-2xl ring-4 ring-white shadow-lg dark:ring-gray-800"
                        data-preview-url="{{ $photo->previewUrl() }}"
                        data-preview-title="{{ $record->name }} — photo"
                        data-preview-pdf="0"
                    >
                        <img
                            src="{{ $photo->previewUrl() }}"
                            alt="{{ $record->name }}"
                            class="h-36 w-28 object-cover transition duration-300 group-hover:scale-105 sm:h-44 sm:w-32"
                        />
                    </button>
                @else
                    <div class="flex h-36 w-28 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-primary-200/80 bg-white/90 dark:border-primary-500/20 dark:bg-white/5 sm:h-44 sm:w-32">
                        <svg class="h-10 w-10 text-primary-300 dark:text-primary-500/50" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span class="mt-1 text-[10px] font-medium text-gray-400">No photo</span>
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30">
                                {{ $record->status->label() }}
                            </span>
                            <span class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $enrollment->enrollment_number }}</span>
                        </div>
                        <h2 class="mt-2 truncate text-xl font-bold text-gray-950 sm:text-2xl dark:text-white">{{ $record->name }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $course?->name ?? '—' }} · {{ $course?->duration_label ?? '' }}</p>
                        @include('filament.pages.partials.student-calling-assignment-banner', [
                            'callingAssignment' => $profile['calling_assignment'] ?? null,
                        ])
                    </div>

                    @if ($enrollment->hasIdCard())
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                wire:click="openIdCardPreview"
                                class="inline-flex min-h-9 items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-500"
                            >
                                View ID card
                            </button>
                            <a
                                href="{{ $enrollment->idCardDownloadUrl() }}"
                                class="inline-flex min-h-9 items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
                            >
                                Download
                            </a>
                            @if (auth()->user()?->hasRole(\App\Enums\RoleName::SuperAdmin->value))
                                <button
                                    type="button"
                                    wire:click="regenerateIdCard"
                                    wire:confirm="Regenerate ID card with the latest student photo and details?"
                                    class="inline-flex min-h-9 items-center rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30"
                                >
                                    Regenerate
                                </button>
                            @endif
                        </div>
                    @elseif ($fees && (float) $fees->paid_amount > 0)
                        <x-filament::button wire:click="generateIdCard" size="sm" color="gray" icon="heroicon-o-identification">
                            Generate ID card
                        </x-filament::button>
                    @endif
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-100 bg-white/80 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Course fee</p>
                        <p class="mt-0.5 text-lg font-bold text-gray-950 dark:text-white">{{ $course?->formatted_fee ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white/80 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Batch / section</p>
                        <p class="mt-0.5 text-lg font-bold text-gray-950 dark:text-white">{{ $batch?->name ?? 'Not assigned' }}</p>
                        @if ($batch?->trainer)
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Trainer: {{ $batch->trainer->name }}</p>
                        @endif
                    </div>
                </div>

                <dl class="mt-4 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Date of birth</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Father's name</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->father_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gender</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->gender?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Mobile</dt>
                        <dd class="mt-0.5 flex flex-wrap items-center gap-2 font-semibold text-gray-950 dark:text-white">
                            <span>{{ $record->mobile }}</span>
                            @include('filament.pages.partials.student-call-button', ['record' => $record])
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Category</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->category?->label() ?? '—' }}</dd>
                    </div>
                </dl>

                @include('filament.pages.partials.student-last-call-summary', ['record' => $record])
            </div>
        </div>
    </div>

    <div @class([
        'grid gap-2 border-t border-gray-100 bg-gray-50/50 px-3 py-3 sm:gap-3 sm:px-5 sm:py-4 dark:border-white/10 dark:bg-white/[0.02]',
        'grid-cols-2 sm:grid-cols-3' => $columnCount === 3,
        'grid-cols-2 sm:grid-cols-5' => $columnCount >= 5,
    ])>
        @foreach ($items as $counter)
            <div @class([
                'rounded-xl px-3 py-2.5 text-center sm:text-left',
                'bg-emerald-500/10 ring-1 ring-emerald-500/20' => $counter['label'] === 'Paid',
                'bg-amber-500/10 ring-1 ring-amber-500/20' => $counter['label'] === 'Pending',
                'bg-white ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10' => ! in_array($counter['label'], ['Paid', 'Pending'], true),
            ])>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $counter['label'] }}</p>
                <p @class([
                    'mt-0.5 truncate text-sm font-bold sm:text-base',
                    'text-emerald-700 dark:text-emerald-400' => $counter['label'] === 'Paid',
                    'text-amber-800 dark:text-amber-400' => $counter['label'] === 'Pending',
                    'text-gray-950 dark:text-white' => ! in_array($counter['label'], ['Paid', 'Pending'], true),
                ])>{{ $counter['value'] }}</p>
            </div>
        @endforeach
    </div>
</div>
