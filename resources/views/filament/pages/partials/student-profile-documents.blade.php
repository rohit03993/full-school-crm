<div>
    @if (! $documentsTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading documents…</p>
    @else
        @include('filament.pages.partials.document-tiles-grid', ['documents' => $documents])
    @endif
</div>
