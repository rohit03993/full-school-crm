@php
    $desk = $this->studentProfileDeskToolbar();
@endphp

@if ($desk['primary'] !== [] || $desk['more'] !== [])
    <div class="fi-student-profile-desk-actions relative z-20 flex w-full min-w-0 items-center gap-1.5 sm:w-auto sm:shrink-0 sm:justify-end">
        @foreach ($desk['primary'] as $action)
            <button
                type="button"
                wire:click="mountAction(@js($action['name']))"
                @class([
                    'inline-flex min-h-8 flex-1 items-center justify-center rounded-lg px-2 text-[11px] font-semibold transition sm:min-h-9 sm:flex-none sm:rounded-full sm:px-3 sm:text-sm',
                    'bg-emerald-600 text-white shadow-sm hover:bg-emerald-500' => $action['tone'] === 'success',
                    'bg-amber-500 text-white shadow-sm hover:bg-amber-400' => $action['tone'] === 'primary',
                    'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/10 dark:text-gray-200 dark:ring-white/15 dark:hover:bg-white/15' => $action['tone'] === 'gray',
                ])
            >
                <span class="sm:hidden">{{ $action['short'] }}</span>
                <span class="hidden sm:inline">{{ $action['label'] }}</span>
            </button>
        @endforeach

        @if ($desk['more'] !== [])
            <x-crm.overflow-menu class="flex-1 sm:flex-none" width-class="min-w-[12.5rem]">
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="inline-flex min-h-8 w-full items-center justify-center gap-0.5 rounded-lg bg-white px-2 text-[11px] font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/10 dark:text-gray-200 dark:ring-white/15 dark:hover:bg-white/15 sm:min-h-9 sm:w-auto sm:rounded-full sm:px-3 sm:text-sm"
                        aria-haspopup="menu"
                    >
                        More
                        <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                </x-slot>
                @foreach ($desk['more'] as $action)
                    <button
                        type="button"
                        wire:click="mountAction(@js($action['name']))"
                        @class([
                            'block w-full px-3 py-2 text-left text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5',
                            'text-rose-700 dark:text-rose-300' => $action['danger'],
                            'text-gray-800 dark:text-gray-100' => ! $action['danger'],
                        ])
                        role="menuitem"
                    >{{ $action['label'] }}</button>
                @endforeach
            </x-crm.overflow-menu>
        @endif
    </div>
@endif
