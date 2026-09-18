@php
    $stacked = $stacked ?? false;
@endphp
<div @class([
    'flex items-center gap-1.5',
    'w-full' => $stacked,
    'justify-end' => ! $stacked,
])>
    <a
        href="{{ $reviewUrl }}"
        @class([
            'inline-flex items-center justify-center rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-primary-500',
            'min-h-9 flex-1' => $stacked,
        ])
    >
        View sheet
    </a>
    <x-crm.overflow-menu>
        <x-slot name="trigger">
            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                aria-label="More actions"
                aria-haspopup="menu"
            >
                <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="h-4 w-4" />
            </button>
        </x-slot>
        @if (filled($pathUrl ?? null))
            <a href="{{ $pathUrl }}" class="block px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5" role="menuitem">
                Subject progress
            </a>
        @endif
        @if ($canRename ?? false)
            <button
                type="button"
                wire:click="startRename({{ \Illuminate\Support\Js::from($groupKey) }}, {{ \Illuminate\Support\Js::from($examLabel ?? '') }})"
                class="block w-full px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                role="menuitem"
            >
                Rename
            </button>
        @endif
        <a href="{{ $excelUrl }}" class="block px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5" role="menuitem">
            {{ $excelLabel }}
        </a>
        @if ($canDelete ?? false)
            <button
                type="button"
                wire:click="deleteExam({{ \Illuminate\Support\Js::from($groupKey) }})"
                wire:confirm="Delete this exam and its marks? Parents have not been messaged. This cannot be undone."
                class="block w-full px-3 py-2 text-left text-sm font-medium text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10"
                role="menuitem"
            >
                Delete
            </button>
        @endif
    </x-crm.overflow-menu>
</div>
