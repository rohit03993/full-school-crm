@php
    use App\Filament\Pages\StudentProfilePage;
@endphp

<div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
    <div class="border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @if (($total ?? 0) > ($shown ?? 0))
            <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                Showing first {{ $shown }} of {{ $total }}.
            </p>
        @endif
    </div>

    @if ($students->isEmpty())
        <div class="px-4 py-8 text-center sm:px-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Callback</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Last call</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($students as $student)
                        @php
                            $followUpAt = $student->next_call_followup_at;
                            $isOverdue = $followUpAt?->isPast() && ! $followUpAt?->isToday();
                            $isDueToday = $followUpAt?->isToday();
                            $enquiry = $student->enquiries->first();
                        @endphp
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                            <td class="whitespace-nowrap px-4 py-3">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $followUpAt?->format('d M Y, h:i A') }}</p>
                                <p @class([
                                    'mt-0.5 text-xs font-medium',
                                    'text-danger-600 dark:text-danger-400' => $isOverdue,
                                    'text-warning-600 dark:text-warning-400' => $isDueToday,
                                    'text-gray-500 dark:text-gray-400' => ! $isOverdue && ! $isDueToday,
                                ])>{{ $worklist->followUpStatusLabel($followUpAt) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $student->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->mobile ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $enquiry?->course?->name ?? 'Not decided' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($student->last_call_at)
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $student->last_call_at->format('d M Y') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $student->last_call_status?->label() ?? '—' }}
                                        @if ($student->lastCall?->staff)
                                            · {{ $student->lastCall->staff->name }}
                                        @endif
                                    </p>
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Not called</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a
                                    href="{{ StudentProfilePage::getUrl(['record' => $student->id]) }}"
                                    class="inline-flex items-center rounded-lg bg-primary-50 px-2.5 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30"
                                >
                                    Open profile
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
