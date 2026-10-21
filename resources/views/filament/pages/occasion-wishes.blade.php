@php
    $summary = $this->summary();
    $rows = $this->wishes();
@endphp

<div class="space-y-6">
    @unless ($this->listReady())
        <section class="rounded-xl bg-white px-4 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-600 dark:text-gray-300">The wish list is not ready yet. Run the database update, then open this page again.</p>
        </section>
    @else
        <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <label class="block text-sm">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Date</span>
                    <input type="date" wire:model.live="on" class="fi-crm-input mt-2 block w-full sm:w-52" />
                </label>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    @if ($this->wishesEnabled())
                        Wishes are <strong class="text-gray-950 dark:text-white">on</strong>.
                    @else
                        Wishes are <strong class="text-gray-950 dark:text-white">off</strong>. This list still fills. Nothing is sent.
                    @endif
                    <a href="{{ $this->automationsUrl() }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Open Automations → Birthdays</a>
                </p>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Student birthdays', 'value' => $summary['student_birthdays']],
                ['label' => 'Staff birthdays', 'value' => $summary['staff_birthdays']],
                ['label' => 'Anniversaries', 'value' => $summary['anniversaries']],
                ['label' => 'Sent', 'value' => $summary['sent']],
                ['label' => 'Pending', 'value' => $summary['pending']],
                ['label' => 'Failed', 'value' => $summary['failed']],
                ['label' => 'Skipped', 'value' => $summary['skipped']],
            ] as $card)
                <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @if ($rows->isEmpty())
                <div class="px-4 py-10 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No birthdays or anniversaries on this date.</p>
                </div>
            @else
                <x-crm.responsive-table>
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Occasion</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mobile</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($rows as $wish)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                    <td class="crm-responsive-table__title px-4 py-3" data-label="">
                                        @if ($wish->student_id)
                                            <a href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $wish->student_id]) }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $wish->recipient_name }}</a>
                                        @elseif ($wish->user_id)
                                            <a href="{{ \App\Filament\Resources\Staff\StaffResource::getUrl('edit', ['record' => $wish->user_id]) }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $wish->recipient_name }}</a>
                                        @else
                                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $wish->recipient_name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200" data-label="Occasion">{{ $wish->occasion->label() }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200" data-label="Mobile">{{ $wish->mobile ?: '—' }}</td>
                                    <td class="px-4 py-3" data-label="Status">
                                        <x-crm.badge :tone="$wish->status->tone()">{{ $wish->status->label() }}</x-crm.badge>
                                    </td>
                                    <td class="crm-responsive-table__wide px-4 py-3 text-sm text-gray-500 dark:text-gray-400" data-label="Note">{{ $wish->skip_reason ?: $wish->last_error ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-crm.responsive-table>
            @endif
        </section>
    @endunless
</div>
