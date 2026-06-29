@props([
    'url',
    'title' => 'Preview',
    'isPdf' => false,
    'label' => 'View',
    'downloadUrl' => null,
    'previewMode' => 'document',
])

<button
    type="button"
    {{ $attributes->class([
        'js-media-preview-trigger inline-flex items-center rounded-lg bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30',
    ]) }}
    data-preview-url="{{ $url }}"
    data-preview-title="{{ $title }}"
    data-preview-pdf="{{ $isPdf ? '1' : '0' }}"
    data-preview-download="{{ $downloadUrl ?? $url }}"
    data-preview-mode="{{ $previewMode }}"
>
    {{ $label }}
</button>
