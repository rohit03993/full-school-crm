@php
    use App\Enums\ExamWindowStatus;
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-5xl lg:pb-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex-1">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Admin creates exams from programme subjects → teachers enter marks → class lead submits → admin approves → publish.
            </p>
        </div>
        <a
            href="{{ $createUrl }}"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500"
        >
            <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
            Create exam
        </a>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-crm-form grid gap-3 sm:grid-cols-2">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search exam, class, section…"
                class="fi-crm-input block w-full"
            />
            <select wire:model.live="statusFilter" class="fi-crm-input block w-full">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($windows->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">No exam windows yet</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Create Unit Test 1 (or any exam) for a section — subjects come from the programme automatically.
            </p>
            <a href="{{ $createUrl }}" class="mt-4 inline-flex items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                Create exam
            </a>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($windows as $window)
                @php
                    $status = $window->status;
                    $statusBadge = match ($status) {
                        \App\Enums\ExamWindowStatus::Draft => 'bg-gray-500/15 text-gray-800 ring-gray-500/20 dark:text-gray-300',
                        \App\Enums\ExamWindowStatus::Open => 'bg-sky-500/15 text-sky-800 ring-sky-500/20 dark:text-sky-300',
                        \App\Enums\ExamWindowStatus::Submitted => 'bg-amber-500/15 text-amber-800 ring-amber-500/20 dark:text-amber-300',
                        \App\Enums\ExamWindowStatus::Approved => 'bg-emerald-500/15 text-emerald-800 ring-emerald-500/20 dark:text-emerald-300',
                    };
                    $entered = $window->subjects->whereNotNull('marks_entered_at')->count();
                    $total = $window->subjects->count();
                @endphp
                <a
                    href="{{ $detailUrl($window->id) }}"
                    class="block rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm transition hover:border-primary-300 dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-primary-500/40 sm:p-5"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-bold text-gray-950 dark:text-white">{{ $window->test_name }}</p>
                                <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ring-1 {{ $statusBadge }}">
                                    {{ $status->label() }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ $displayBatch($window) }}
                                <span class="text-gray-300 dark:text-gray-600"> · </span>
                                {{ $window->session_date?->format('d M Y') }}
                                @if ($window->activityType)
                                    <span class="text-gray-300 dark:text-gray-600"> · </span>
                                    {{ $window->activityType->name }}
                                @endif
                            </p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Marks entered: <strong class="text-gray-700 dark:text-gray-300">{{ $entered }}/{{ $total }}</strong> subjects
                            </p>
                        </div>
                        <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">Open →</span>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($windows->hasPages())
            <div class="pt-2">
                {{ $windows->links() }}
            </div>
        @endif
    @endif
</div>
