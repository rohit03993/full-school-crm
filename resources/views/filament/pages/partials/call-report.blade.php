@php
    use App\Filament\Pages\StudentProfilePage;
@endphp

<div class="mx-auto max-w-6xl space-y-4 pb-24 lg:pb-6">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            ['label' => 'Total calls', 'value' => $summary['total'] ?? 0, 'tone' => ''],
            ['label' => 'Connected', 'value' => $summary['connected'] ?? 0, 'tone' => 'text-emerald-700 dark:text-emerald-400'],
            ['label' => 'Not connected', 'value' => $summary['not_connected'] ?? 0, 'tone' => 'text-danger-600 dark:text-danger-400'],
            ['label' => 'New calls', 'value' => $summary['new_calls'] ?? 0, 'tone' => 'text-primary-700 dark:text-primary-400'],
            ['label' => 'Follow-up calls', 'value' => $summary['followup_calls'] ?? 0, 'tone' => 'text-amber-700 dark:text-amber-400'],
        ] as $stat)
            <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p @class(['mt-1 text-2xl font-bold text-gray-950 dark:text-white', $stat['tone']])>{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-crm-form grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">From</label>
                <input type="date" wire:model.live="dateFrom" class="fi-crm-input mt-2 block w-full" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">To</label>
                <input type="date" wire:model.live="dateTo" class="fi-crm-input mt-2 block w-full" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Connection</label>
                <x-crm.select wire:model.live="connectionFilter" class="mt-2">
                    <option value="all">All</option>
                    <option value="connected">Connected</option>
                    <option value="not_connected">Not connected</option>
                </x-crm.select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Call type</label>
                <x-crm.select wire:model.live="callTypeFilter" class="mt-2">
                    <option value="all">All</option>
                    <option value="new">New (first-ever)</option>
                    <option value="followup">Follow-up</option>
                </x-crm.select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Not connected reason</label>
                <x-crm.select wire:model.live="callStatusFilter" class="mt-2">
                    <option value="">Any</option>
                    @foreach ($notConnectedOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Visit status set to</label>
                <x-crm.select wire:model.live="visitStatusFilter" class="mt-2">
                    <option value="">Any</option>
                    @foreach ($visitStatusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select>
            </div>
            @if ($canViewAllStaff)
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Staff</label>
                    <x-crm.select wire:model.live="staffUserId" class="mt-2">
                        <option value="">All staff</option>
                        @foreach ($staffOptions as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </x-crm.select>
                </div>
            @endif
            <div @class(['sm:col-span-2' => ! $canViewAllStaff, 'lg:col-span-2' => $canViewAllStaff])>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Search student</label>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Name, mobile, parent name"
                    class="fi-crm-input mt-2 block w-full"
                />
            </div>
        </div>

        <div class="mt-3 flex justify-end">
            <button
                type="button"
                wire:click="resetFilters"
                class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5"
            >
                Reset filters
            </button>
        </div>
    </div>

    <div class="fi-section overflow-hidden rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        @if (! $calls || $calls->isEmpty())
            <div class="px-4 py-10 text-center sm:px-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">No calls match these filters.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">When</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Student</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Caller</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($calls as $call)
                            @php
                                $isNew = (int) ($firstCallIds[$call->student_id] ?? 0) === (int) $call->id;
                            @endphp
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $call->called_at?->format('d M Y, h:i A') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $call->call_direction->label() }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $call->student?->name ?? '—' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $call->student?->mobile ?? '—' }}</p>
                                    @if ($call->enquiry?->course)
                                        <p class="mt-0.5 text-xs text-gray-400">{{ $call->enquiry->course->name }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $call->staff?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $call->call_status->isConnected(),
                                        'bg-danger-100 text-danger-800 dark:bg-danger-500/15 dark:text-danger-300' => ! $call->call_status->isConnected(),
                                    ])>
                                        {{ $call->call_status->label() }}
                                    </span>
                                    @if ($call->visit_status_changed_to)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $call->visit_status_changed_to->label() }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                        'bg-primary-500/10 text-primary-700 dark:text-primary-300' => $isNew,
                                        'bg-amber-500/10 text-amber-800 dark:text-amber-300' => ! $isNew,
                                    ])>
                                        {{ $isNew ? 'New' : 'Follow-up' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $call->call_notes ? \Illuminate\Support\Str::limit($call->call_notes, 80) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ($call->student_id)
                                        <a
                                            href="{{ StudentProfilePage::getUrl(['record' => $call->student_id, 'tab' => 'calls']) }}"
                                            class="inline-flex items-center rounded-lg bg-primary-50 px-2.5 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30"
                                        >
                                            Profile
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($calls->hasPages())
                <div class="border-t border-gray-100 px-4 py-3 dark:border-white/10">
                    {{ $calls->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
