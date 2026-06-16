@if (! $rosterLoaded)
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Open this page from Practicals, Industrial Visits, or Seminars using <span class="font-semibold">Mark Attendance</span>.
    </div>
@elseif ($roster->isEmpty())
    <div class="rounded-xl bg-amber-50 px-4 py-8 text-center text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
        No active students in this batch. Assign students to the batch first.
    </div>
@else
    <div class="space-y-4">
        @if ($activityTitle)
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $activityTitle }}</p>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $roster->count() }} student(s)</p>
            <div class="flex flex-wrap gap-2">
                <x-filament::button wire:click="markAllPresent" size="sm" color="gray" icon="heroicon-o-check-circle">
                    Mark all Present
                </x-filament::button>
                <x-filament::button wire:click="saveAttendance" size="sm" color="success" icon="heroicon-o-check">
                    Save
                </x-filament::button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($roster as $row)
                    @php
                        $student = $row->student;
                        $present = (bool) ($marks[$student->id] ?? false);
                    @endphp
                    <div class="flex flex-col gap-3 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:bg-gray-900">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $student->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->mobile }}</p>
                        </div>
                        <div class="flex shrink-0 gap-1.5">
                            <button
                                type="button"
                                wire:click="$set('marks.{{ $student->id }}', true)"
                                @class([
                                    'min-w-[4.5rem] rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide ring-1 transition',
                                    'bg-emerald-500 text-white ring-emerald-600' => $present,
                                    'bg-gray-50 text-gray-600 ring-gray-200 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => ! $present,
                                ])
                            >
                                Present
                            </button>
                            <button
                                type="button"
                                wire:click="$set('marks.{{ $student->id }}', false)"
                                @class([
                                    'min-w-[4.5rem] rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide ring-1 transition',
                                    'bg-rose-500 text-white ring-rose-600' => ! $present,
                                    'bg-gray-50 text-gray-600 ring-gray-200 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => $present,
                                ])
                            >
                                Absent
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <x-filament::button wire:click="saveAttendance" size="lg" color="success" icon="heroicon-o-check" class="w-full sm:w-auto">
                Save Attendance
            </x-filament::button>
        </div>
    </div>
@endif
