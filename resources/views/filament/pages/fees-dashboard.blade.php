<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="setActiveTab('overview')"
                @class([
                    'rounded-lg px-4 py-2 text-sm font-semibold transition',
                    'bg-primary-600 text-white shadow-sm' => $activeTab === 'overview',
                    'bg-white text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5' => $activeTab !== 'overview',
                ])
            >
                Overview
            </button>
            <button
                type="button"
                wire:click="setActiveTab('ledger')"
                @class([
                    'rounded-lg px-4 py-2 text-sm font-semibold transition',
                    'bg-primary-600 text-white shadow-sm' => $activeTab === 'ledger',
                    'bg-white text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5' => $activeTab !== 'ledger',
                ])
            >
                Fee ledger
            </button>
        </div>

        @if ($activeTab === 'overview')
            @include('filament.pages.partials.fees-overview-tab')
        @else
            @include('filament.pages.partials.fees-ledger-tab')
        @endif
    </div>
</x-filament-panels::page>
