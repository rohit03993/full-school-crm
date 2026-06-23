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

    @if ($visits->isEmpty())
        <div class="px-4 py-8 text-center sm:px-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Follow-up</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Visit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Staff</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($visits as $visit)
                        @php
                            $followUpDate = $visit->next_follow_up_date;
                            $isOverdue = $followUpDate?->isPast() && ! $followUpDate?->isToday();
                            $isDueToday = $followUpDate?->isToday();
                        @endphp
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                            <td class="whitespace-nowrap px-4 py-3">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $followUpDate?->format('d M Y') }}</p>
                                <p @class([
                                    'mt-0.5 text-xs font-medium',
                                    'text-danger-600 dark:text-danger-400' => $isOverdue,
                                    'text-warning-600 dark:text-warning-400' => $isDueToday,
                                    'text-gray-500 dark:text-gray-400' => ! $isOverdue && ! $isDueToday,
                                ])>{{ $worklist->followUpStatusLabel($followUpDate) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $visit->student?->name ?? '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $visit->student?->mobile ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $visit->enquiry?->course?->name ?? 'Not decided' }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $visit->visit_date?->format('d M Y') ?? '—' }}</p>
                                @if ($visit->status)
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $visit->status->label() }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $visit->staff?->name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if ($visit->student_id)
                                    <a
                                        href="{{ StudentProfilePage::getUrl(['record' => $visit->student_id]) }}"
                                        class="inline-flex items-center rounded-lg bg-primary-50 px-2.5 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30"
                                    >
                                        Open profile
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
