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
    <div class="bg-gradient-to-r from-primary-500/10 via-white to-emerald-500/5 px-4 py-5 dark:from-primary-500/10 dark:via-gray-900 dark:to-emerald-500/5 sm:px-6 sm:py-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:gap-6">
            {{-- Student photo --}}
            <div class="flex shrink-0 flex-col items-center lg:items-start">
                @if ($photo && $photo->isImage())
                    <button
                        type="button"
                        class="cursor-zoom-in"
                        data-preview-url="{{ $photo->previewUrl() }}"
                        data-preview-title="{{ $record->name }} — photo"
                        data-preview-pdf="0"
                        x-data
                        x-on:click="$dispatch('open-media-preview', { url: $el.dataset.previewUrl, title: $el.dataset.previewTitle, isPdf: false })"
                    >
                        <img
                            src="{{ $photo->previewUrl() }}"
                            alt="{{ $record->name }}"
                            class="h-36 w-28 rounded-xl object-cover shadow-lg ring-4 ring-white dark:ring-gray-800 sm:h-44 sm:w-32"
                        />
                    </button>
                @else
                    <div class="flex h-36 w-28 items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white/80 dark:border-white/20 dark:bg-white/5 sm:h-44 sm:w-32">
                        <svg class="h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                    </div>
                @endif
                <p class="mt-2 font-mono text-[10px] font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Student dossier</p>
            </div>

            {{-- Identity --}}
            <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="truncate text-xl font-bold text-gray-950 sm:text-2xl dark:text-white">{{ $record->name }}</h2>
                        <p class="mt-1 font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $enrollment->enrollment_number }}</p>
                    </div>
                    <div class="flex flex-col items-start gap-2 sm:items-end">
                        <span class="inline-flex w-fit shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30">
                            {{ $record->status->label() }}
                        </span>

                        @if ($enrollment->hasIdCard())
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    wire:click="openIdCardPreview"
                                    class="inline-flex items-center rounded-lg bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30"
                                >
                                    View ID Card
                                </button>
                                <a
                                    href="{{ $enrollment->idCardDownloadUrl() }}"
                                    class="inline-flex items-center rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
                                >
                                    Download
                                </a>
                                @if (auth()->user()?->hasRole(\App\Enums\RoleName::SuperAdmin->value))
                                    <button
                                        type="button"
                                        wire:click="regenerateIdCard"
                                        wire:confirm="Regenerate ID card with the latest student photo and details?"
                                        class="inline-flex items-center rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30"
                                    >
                                        Regenerate
                                    </button>
                                @endif
                            </div>
                        @elseif ($fees && (float) $fees->paid_amount > 0)
                            <x-filament::button wire:click="generateIdCard" size="sm" color="gray" icon="heroicon-o-identification">
                                Generate ID Card
                            </x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course enrolled</p>
                        <p class="mt-0.5 text-base font-bold text-gray-950 dark:text-white">{{ $course?->name ?? '—' }}</p>
                        @if ($course)
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $course->duration_label }} · {{ $course->formatted_fee }} course fee</p>
                        @endif
                    </div>
                    <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Batch</p>
                        <p class="mt-0.5 text-base font-bold text-gray-950 dark:text-white">{{ $batch?->name ?? 'Not assigned' }}</p>
                        @if ($batch?->trainer)
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">Trainer: {{ $batch->trainer->name }}</p>
                        @endif
                    </div>
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date of birth</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Father's name</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->father_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Gender</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->gender?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mobile</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->mobile }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="mt-0.5 truncate font-semibold text-gray-950 dark:text-white">{{ $record->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Category</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $record->category?->label() ?? '—' }}</dd>
                    </div>
                </dl>

                @if (filled($record->address) || filled($record->city))
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Address:</span>
                        {{ collect([$record->address, $record->city, $record->state, $record->pincode])->filter()->implode(', ') }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick stats --}}
    <div @class([
        'grid gap-2 border-t border-gray-100 px-4 py-3 sm:gap-3 sm:px-6 sm:py-4 dark:border-white/10',
        'grid-cols-2 sm:grid-cols-3' => $columnCount === 3,
        'grid-cols-2 sm:grid-cols-5' => $columnCount >= 5,
    ])>
        @foreach ($items as $counter)
            <div @class([
                'rounded-xl px-3 py-2.5',
                'bg-emerald-500/10 ring-1 ring-emerald-500/15' => in_array($counter['label'], ['Paid'], true),
                'bg-amber-500/10 ring-1 ring-amber-500/15' => $counter['label'] === 'Pending',
                'bg-gray-50 dark:bg-white/5' => ! in_array($counter['label'], ['Paid', 'Pending'], true),
            ])>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $counter['label'] }}</p>
                <p @class([
                    'mt-0.5 truncate text-base font-bold sm:text-lg',
                    'text-emerald-700 dark:text-emerald-400' => $counter['label'] === 'Paid',
                    'text-amber-800 dark:text-amber-400' => $counter['label'] === 'Pending',
                    'text-gray-950 dark:text-white' => ! in_array($counter['label'], ['Paid', 'Pending'], true),
                ])>{{ $counter['value'] }}</p>
            </div>
        @endforeach
    </div>
</div>
