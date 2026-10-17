@if ($showReportStudent ?? false)
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4" wire:click.self="closeReportStudent">
        <div class="flex h-[100dvh] w-full flex-col bg-gray-50 shadow-xl dark:bg-gray-950 sm:h-auto sm:max-h-[90vh] sm:max-w-2xl sm:rounded-2xl">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-100 bg-white px-4 py-3 dark:border-white/10 dark:bg-gray-900 sm:rounded-t-2xl sm:px-5 sm:py-4">
                <div class="min-w-0">
                    <h3 class="truncate text-lg font-bold text-gray-950 dark:text-white">Report student</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $studentName ?? '' }}
                        @if (($reportCount ?? 0) > 0)
                            · {{ $reportCount }} {{ $reportCount === 1 ? 'report' : 'reports' }}
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="closeReportStudent" class="inline-flex min-h-11 shrink-0 items-center px-2 text-sm font-semibold text-gray-600 dark:text-gray-300">Close</button>
            </div>
            <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                @include('filament.pages.partials.student-profile-conduct')
            </div>
        </div>
    </div>
@endif
