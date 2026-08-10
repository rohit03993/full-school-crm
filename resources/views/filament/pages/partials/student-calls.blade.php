<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-4xl lg:pb-6">
    <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20">
        Service calls for enrolled students (fees, attendance, documents, etc.). Lead telecalling stays under <strong>Calls → Call Queue / Call Report</strong>.
    </div>

    <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:max-w-xs">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Matching calls</p>
        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stats['total'] ?? 0 }}</p>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-crm-form grid gap-3 sm:grid-cols-2">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search student name or mobile…"
                class="fi-crm-input block w-full sm:col-span-2"
            />

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">From</label>
                <input type="date" wire:model.live="dateFrom" class="fi-crm-input mt-1 block w-full" />
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">To</label>
                <input type="date" wire:model.live="dateTo" class="fi-crm-input mt-1 block w-full" />
            </div>

            <x-crm.select wire:model.live="purposeFilter" class="w-full">
                <option value="">All purposes</option>
                @foreach ($purposeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-crm.select>

            <x-crm.select wire:model.live="staffFilter" class="w-full">
                <option value="">All staff</option>
                @foreach ($staffOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-crm.select>

            <div class="sm:col-span-2">
                <button type="button" wire:click="resetFilters" class="text-sm font-semibold text-primary-600 hover:text-primary-500">
                    Reset filters
                </button>
            </div>
        </div>
    </div>

    @if ($calls->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">No student calls match</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try another purpose, date range, or staff member.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="hidden grid-cols-12 gap-3 border-b border-gray-100 bg-gray-50 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400 sm:grid sm:px-6">
                <div class="col-span-3">Student</div>
                <div class="col-span-2">Purpose</div>
                <div class="col-span-3">Date</div>
                <div class="col-span-2">Staff</div>
                <div class="col-span-2">Status</div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($calls as $call)
                    @php
                        $student = $call->student;
                        $profileUrl = $student
                            ? \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $student->id, 'tab' => 'calls'])
                            : '#';
                    @endphp
                    <a href="{{ $profileUrl }}" class="block px-4 py-4 transition hover:bg-primary-500/[0.03] sm:px-6">
                        <div class="grid gap-2 sm:grid-cols-12 sm:items-center sm:gap-3">
                            <div class="sm:col-span-3">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $student?->name ?? '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student?->mobile ?? '' }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                @if ($call->call_purpose)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        {{ $call->call_purpose->label() }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-500">—</span>
                                @endif
                            </div>
                            <div class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-3">
                                {{ $call->called_at?->format('d M Y, h:i A') }}
                            </div>
                            <div class="text-sm font-medium text-violet-700 dark:text-violet-300 sm:col-span-2">
                                {{ $call->staff?->name ?? '—' }}
                            </div>
                            <div class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">
                                {{ $call->call_status->label() }}
                            </div>
                        </div>
                        @if (filled($call->call_notes))
                            <p class="mt-2 line-clamp-2 text-xs text-gray-500 dark:text-gray-400 sm:pl-0">{{ $call->call_notes }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        @if ($calls->hasPages())
            <div class="pt-2">
                {{ $calls->links() }}
            </div>
        @endif
    @endif
</div>
