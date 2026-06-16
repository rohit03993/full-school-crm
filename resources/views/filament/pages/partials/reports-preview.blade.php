@if (! $report)
    <div class="rounded-xl bg-gray-50 px-4 py-10 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Choose a report and tap <span class="font-semibold">Generate Report</span> to preview results here.
    </div>
@else
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-950 dark:text-white">{{ $report['title'] }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Generated {{ $report['generated_at'] }} · {{ count($report['rows']) }} row(s)</p>
            </div>
            @if ($canExport)
                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="exportExcel" size="sm" color="primary" icon="heroicon-o-table-cells" class="touch-manipulation">
                        Export Excel
                    </x-filament::button>
                    <x-filament::button wire:click="exportCsv" size="sm" color="gray" icon="heroicon-o-arrow-down-tray" class="touch-manipulation">
                        Export CSV
                    </x-filament::button>
                    <x-filament::button wire:click="exportPdf" size="sm" color="success" icon="heroicon-o-document-arrow-down" class="touch-manipulation">
                        Export PDF
                    </x-filament::button>
                </div>
            @endif
        </div>

        <p class="fi-crm-scroll-hint">Swipe horizontally to view all columns.</p>
        <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        @foreach ($report['columns'] as $column)
                            <th class="px-4 py-2.5 whitespace-nowrap">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($report['rows'] as $row)
                        <tr class="bg-white dark:bg-gray-900">
                            @foreach ($row as $cell)
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($report['columns']) }}" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                No records for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
