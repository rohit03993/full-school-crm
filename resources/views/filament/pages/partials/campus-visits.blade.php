@php
    use App\Filament\Pages\StudentProfilePage;
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-6xl lg:pb-6">
  <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-wrap gap-2">
      <button
        type="button"
        wire:click="setPeriodToday"
        class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300"
      >
        Today
      </button>
      <button
        type="button"
        wire:click="setPeriodThisMonth"
        class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300"
      >
        This month
      </button>
    </div>

    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <label class="block">
        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">From</span>
        <input type="date" wire:model.live="dateFrom" class="fi-crm-input mt-1 block w-full" />
      </label>
      <label class="block">
        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">To</span>
        <input type="date" wire:model.live="dateTo" class="fi-crm-input mt-1 block w-full" />
      </label>
      <label class="block sm:col-span-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Search</span>
        <input
          type="search"
          wire:model.live.debounce.300ms="search"
          placeholder="Student, mobile, enquiry no., staff"
          class="fi-crm-input mt-1 block w-full"
        />
      </label>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
      @foreach (['all' => 'All visits', 'prospect' => 'New / prospect', 'enrolled' => 'Enrolled students'] as $value => $label)
        <button
          type="button"
          wire:click="$set('enrollmentFilter', '{{ $value }}')"
          @class([
            'rounded-full px-3 py-1.5 text-xs font-semibold transition',
            'bg-primary-600 text-white shadow-sm' => $enrollmentFilter === $value,
            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300' => $enrollmentFilter !== $value,
          ])
        >
          {{ $label }}
        </button>
      @endforeach
    </div>

    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
      Showing visits for <strong>{{ $periodLabel }}</strong>.
    </p>
  </div>

  <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ([
      ['label' => 'Total visits', 'value' => $stats['total_visits'] ?? 0],
      ['label' => 'Unique students', 'value' => $stats['unique_students'] ?? 0],
      ['label' => 'New / prospect', 'value' => $stats['prospect_visits'] ?? 0],
      ['label' => 'Enrolled', 'value' => $stats['enrolled_visits'] ?? 0],
      ['label' => 'First-time visitors', 'value' => $stats['first_time_visitors'] ?? 0],
      ['label' => 'Visited 2+ times', 'value' => $stats['repeat_visit_students'] ?? 0],
    ] as $stat)
      <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
      </div>
    @endforeach
  </div>

  @if ($visits->isEmpty())
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
      <p class="text-lg font-semibold text-gray-950 dark:text-white">No visits in this period</p>
      <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Change the date range or filters above.</p>
    </div>
  @else
    <div class="fi-section overflow-hidden rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 dark:divide-white/10">
          <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Student</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Logged by</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-gray-900">
            @foreach ($visits as $visit)
              @php
                $isEnrolled = $visit->student?->activeEnrollment !== null;
              @endphp
              <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-950 dark:text-white">
                  {{ $visit->visit_date?->format('d M Y') ?? '—' }}
                </td>
                <td class="px-4 py-3">
                  <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $visit->student?->name ?? '—' }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ $visit->student?->mobile ?? '—' }}</p>
                </td>
                <td class="px-4 py-3">
                  <span @class([
                    'inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ring-1',
                    'bg-emerald-500/15 text-emerald-700 ring-emerald-500/20 dark:text-emerald-400' => $isEnrolled,
                    'bg-sky-500/15 text-sky-700 ring-sky-500/20 dark:text-sky-400' => ! $isEnrolled,
                  ])>
                    {{ $isEnrolled ? 'Enrolled' : 'Prospect' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                  {{ $visit->enquiry?->course?->name ?? 'Not decided' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                  {{ $visit->status?->label() ?? '—' }}
                </td>
                <td class="px-4 py-3 text-sm font-medium text-gray-950 dark:text-white">
                  {{ $visit->staff?->name ?? '—' }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                  @if ($visit->student_id)
                    <a
                      href="{{ StudentProfilePage::getUrl(['record' => $visit->student_id]) }}"
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
    </div>

    <div class="pt-2">
      {{ $visits->links() }}
    </div>
  @endif
</div>
