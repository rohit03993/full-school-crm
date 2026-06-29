@props([
    'document' => null,
    'label',
    'variant' => 'default',
])

@php
    $isSignature = $variant === 'signature';
@endphp

<div {{ $attributes->class([
    'flex flex-col overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10',
    'bg-white dark:bg-gray-900' => true,
]) }}>
    <div class="border-b border-gray-100 px-3 py-2 dark:border-white/10">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
    </div>

    <div @class([
        'flex flex-1 flex-col items-center justify-center bg-gray-50 p-3 dark:bg-white/5',
        'min-h-36' => ! $isSignature,
        'min-h-28' => $isSignature,
    ])>
        @if ($document && $document->isImage())
            <button
                type="button"
                class="js-media-preview-trigger block w-full cursor-zoom-in"
                data-preview-url="{{ $document->previewUrl() }}"
                data-preview-title="{{ $label }}"
                data-preview-pdf="0"
            >
                <img
                    src="{{ $document->previewUrl() }}"
                    alt="{{ $label }}"
                    @class([
                        'mx-auto rounded-lg object-contain shadow-sm ring-1 ring-gray-200 dark:ring-white/10',
                        'max-h-40 w-full' => ! $isSignature,
                        'max-h-24 w-full bg-white' => $isSignature,
                    ])
                />
            </button>
        @elseif ($document)
            <div class="flex flex-col items-center gap-2 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
                <p class="line-clamp-2 text-xs text-gray-600 dark:text-gray-400">{{ $document->original_filename }}</p>
            </div>
        @else
            <p class="text-sm font-medium text-danger-600 dark:text-danger-400">Not uploaded</p>
        @endif
    </div>

    @if ($document)
        <div class="flex flex-wrap gap-2 border-t border-gray-100 p-3 dark:border-white/10">
            @if ($document->isPreviewableInBrowser())
                <x-crm.media-preview-button
                    :url="$document->previewUrl()"
                    :title="$label"
                    :is-pdf="! $document->isImage()"
                    label="View"
                    class="flex-1 justify-center bg-gray-100 text-gray-700 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
                />
            @endif
            <a
                href="{{ $document->downloadUrl() }}"
                class="inline-flex flex-1 items-center justify-center rounded-lg bg-primary-50 px-2 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30"
            >
                Download
            </a>
        </div>
        <p class="border-t border-gray-100 px-3 py-2 text-[10px] text-gray-400 dark:border-white/10">
            {{ $document->created_at?->format('d M Y, h:i A') }}
        </p>
    @endif
</div>
