@php
    $enrollment = $record->activeEnrollment;
    $previewUrl = $enrollment?->hasIdCard()
        ? $enrollment->idCardPreviewUrl().'?v='.$enrollment->id_card_generated_at?->timestamp
        : null;
@endphp

@if ($showIdCardPreview && $previewUrl)
    @teleport('body')
        <div
            class="fixed inset-0 z-[99999] flex items-center justify-center p-4 sm:p-6"
            role="dialog"
            aria-modal="true"
            wire:keydown.escape.window="closeIdCardPreview"
        >
            <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" wire:click="closeIdCardPreview"></div>

            <div
                class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-gray-950 shadow-2xl ring-1 ring-white/10"
                wire:click.stop
            >
                <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 sm:px-5">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-amber-400">Student ID</p>
                        <h3 class="text-sm font-semibold text-white">{{ $enrollment->enrollment_number }}</h3>
                    </div>
                    <button
                        type="button"
                        wire:click="closeIdCardPreview"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-300 hover:bg-white/10 hover:text-white"
                        aria-label="Close"
                    >
                        ✕
                    </button>
                </div>

                <div class="bg-[#0a0a0a] px-4 py-6 sm:px-6 sm:py-8">
                    <div class="relative mx-auto w-full max-w-xl overflow-hidden rounded-xl bg-white p-1 shadow-2xl ring-2 ring-amber-500/40 aspect-[85.6/54]">
                        <iframe
                            src="{{ $previewUrl }}#toolbar=0&navpanes=0&scrollbar=0&view=Fit"
                            title="Student ID card preview"
                            class="absolute inset-0 h-full w-full rounded-lg border-0 bg-white"
                        ></iframe>
                    </div>
                    <p class="mt-3 text-center text-[11px] text-gray-500">Landscape ID card · 86 × 54 mm</p>
                </div>

                <div class="flex justify-end gap-2 border-t border-white/10 bg-gray-900/80 px-4 py-3 sm:px-5">
                    <a
                        href="{{ $enrollment->idCardDownloadUrl() }}"
                        class="inline-flex items-center rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-gray-200 ring-1 ring-white/10 hover:bg-white/15"
                    >
                        Download ID Card
                    </a>
                    <button
                        type="button"
                        wire:click="closeIdCardPreview"
                        class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endteleport
@endif
