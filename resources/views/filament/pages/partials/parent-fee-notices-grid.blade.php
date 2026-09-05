@if ($rows === [])
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Select a class / batch above to load students.
    </div>
@else
    <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2.5">Send</th>
                    <th class="px-3 py-2.5">Student</th>
                    <th class="px-3 py-2.5">Roll</th>
                    <th class="px-3 py-2.5">Mobile</th>
                    <th class="px-3 py-2.5">Amount pending</th>
                    <th class="px-3 py-2.5">Due date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $index => $row)
                    <tr @class([
                        'bg-white dark:bg-gray-900',
                        'opacity-60' => ! ($row['has_mobile'] ?? false),
                    ])>
                        <td class="px-3 py-2">
                            <input
                                type="checkbox"
                                wire:model.live="rows.{{ $index }}.include"
                                @disabled(! ($row['has_mobile'] ?? false))
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                        </td>
                        <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                            {{ $row['name'] }}
                        </td>
                        <td class="px-3 py-2 font-mono text-gray-600 dark:text-gray-300">
                            {{ $row['roll'] }}
                        </td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                            @if ($row['has_mobile'] ?? false)
                                {{ $row['mobile'] }}
                            @else
                                <span class="text-amber-600 dark:text-amber-400">No mobile</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model="rows.{{ $index }}.amount"
                                @disabled(! ($row['has_mobile'] ?? false))
                                placeholder="0.00"
                                class="w-full min-w-[7rem] rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                type="date"
                                wire:model="rows.{{ $index }}.due_date"
                                @disabled(! ($row['has_mobile'] ?? false))
                                class="w-full min-w-[9rem] rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                            />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        {{ collect($rows)->where('include', true)->count() }} selected ·
        {{ collect($rows)->where('has_mobile', false)->count() }} without mobile (cannot send)
    </p>
@endif
