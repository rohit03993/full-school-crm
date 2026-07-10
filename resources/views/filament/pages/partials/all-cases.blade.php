<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-4xl lg:pb-6">
    <div class="rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/20">
        Supervisor overview only. Staff see cases under <strong>My work → My cases</strong> when assigned to them. Only the current assignee can transfer, close, or log calls.
    </div>

    <div class="grid grid-cols-3 gap-3 sm:grid-cols-3">
        @foreach ([
            ['label' => 'Open institute-wide', 'value' => $stats['open'] ?? 0],
            ['label' => 'Closed', 'value' => $stats['closed'] ?? 0],
            ['label' => 'Total', 'value' => $stats['total'] ?? 0],
        ] as $stat)
            <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-crm-form grid gap-3 sm:grid-cols-2">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search case no., student, title…"
                class="fi-crm-input block w-full sm:col-span-2"
            />

            <x-crm.select wire:model.live="statusFilter" class="w-full">
                <option value="open">Open cases</option>
                <option value="closed">Closed cases</option>
                <option value="all">All statuses</option>
            </x-crm.select>

            <x-crm.select wire:model.live="assigneeFilter" class="w-full">
                <option value="">All assignees</option>
                @foreach ($staffOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-crm.select>

            <x-crm.select wire:model.live="caseTypeFilter" class="w-full sm:col-span-2">
                <option value="">All case types</option>
                @foreach ($caseTypeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-crm.select>
        </div>
    </div>

    @if ($cases->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">No cases match your filters</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="hidden grid-cols-12 gap-3 border-b border-gray-100 bg-gray-50 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400 sm:grid sm:px-6">
                <div class="col-span-3">Case</div>
                <div class="col-span-3">Student</div>
                <div class="col-span-2">Type</div>
                <div class="col-span-2">Assignee</div>
                <div class="col-span-2">Opened</div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($cases as $case)
                    @php
                        $student = $case->student;
                    @endphp
                    <a
                        href="{{ $student ? \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $student->id, 'tab' => 'cases', 'case' => $case->id]) : '#' }}"
                        class="block px-4 py-4 transition hover:bg-primary-500/[0.03] sm:px-6"
                    >
                        <div class="grid gap-2 sm:grid-cols-12 sm:items-center sm:gap-3">
                            <div class="sm:col-span-3">
                                <p class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400">{{ $case->case_number }}</p>
                                <p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">{{ \Illuminate\Support\Str::limit($case->title, 48) }}</p>
                                <span @class([
                                    'mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' => $case->isOpen(),
                                    'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $case->isOpen(),
                                ])>{{ $case->status->label() }}</span>
                            </div>
                            <div class="sm:col-span-3">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $student?->name ?? '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student?->mobile ?? '' }}</p>
                            </div>
                            <div class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ $case->case_type->label() }}</div>
                            <div class="text-sm font-medium text-violet-700 dark:text-violet-300 sm:col-span-2">{{ $case->currentAssignee?->name ?? '—' }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 sm:col-span-2">{{ $case->opened_at?->format('d M Y') }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        @if ($cases->hasPages())
            <div class="pt-2">
                {{ $cases->links() }}
            </div>
        @endif
    @endif
</div>
