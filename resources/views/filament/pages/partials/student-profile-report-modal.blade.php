@if ($showReportStudent ?? false)
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" wire:click.self="closeReportStudent">
        <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-gray-50 shadow-xl dark:bg-gray-950 sm:rounded-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-gray-100 bg-white px-4 py-4 dark:border-white/10 dark:bg-gray-900 sm:px-6">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Report student</h3>
                <button type="button" wire:click="closeReportStudent" class="shrink-0 text-sm font-semibold text-gray-500">Close</button>
            </div>
            <div class="px-4 py-4 sm:px-6 sm:py-5">
                @include('filament.pages.partials.student-profile-conduct')
            </div>
        </div>
    </div>
@endif
