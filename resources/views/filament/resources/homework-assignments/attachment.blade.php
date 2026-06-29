@php
    /** @var \App\Models\HomeworkAssignment $record */
    $isPdf = $record->content_type === \App\Enums\HomeworkContentType::Pdf;
@endphp

@if ($record->hasFile() && $record->isPreviewable())
    <div class="flex flex-wrap items-center gap-2">
        <x-crm.media-preview-button
            :url="$record->staffPreviewUrl()"
            :title="$record->title"
            :is-pdf="$isPdf"
            :download-url="$record->staffDownloadUrl()"
            label="View attachment"
        />
        <a
            href="{{ $record->staffPreviewUrl() }}"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
        >
            Open in new tab
        </a>
        <a
            href="{{ $record->staffDownloadUrl() }}"
            class="inline-flex items-center rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
        >
            Download
        </a>
    </div>
@elseif ($record->hasFile())
    <p class="text-sm text-gray-500 dark:text-gray-400">Attachment uploaded.</p>
@endif
