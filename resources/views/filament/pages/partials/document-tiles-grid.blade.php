@props([
    'documents',
    'emptyMessage' => 'No documents uploaded yet.',
])

@if ($documents->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</p>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($documents as $document)
            <x-crm.admission-document-tile
                :document="$document"
                :label="$document->type->label()"
                :variant="$document->type->value === 'signature' ? 'signature' : 'default'"
            />
        @endforeach
    </div>
@endif
