@php
    use App\Filament\Pages\StudentProfilePage;
@endphp

<div class="mx-auto max-w-lg space-y-3 pb-24 lg:max-w-6xl lg:space-y-4 lg:pb-6">
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-gray-950/[0.06] sm:p-4 dark:bg-gray-900 dark:ring-white/10">
    <div class="crm-seg" role="group" aria-label="Period">
      <button
        type="button"
        wire:click="setPeriodToday"
        @class(['crm-seg__btn', 'crm-seg__btn--active' => $periodPreset === 'today'])
      >
        Today
      </button>
      <button
        type="button"
        wire:click="setPeriodThisWeek"
        @class(['crm-seg__btn', 'crm-seg__btn--active' => $periodPreset === 'week'])
      >
        This week
      </button>
      <button
        type="button"
        wire:click="setPeriodThisMonth"
        @class(['crm-seg__btn', 'crm-seg__btn--active' => $periodPreset === 'month'])
      >
        This month
      </button>
    </div>

    <div class="relative mt-3">
      <svg class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.2-5.2m2.2-5.3a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
      </svg>
      <input
        type="search"
        wire:model.live.debounce.300ms="search"
        placeholder="Search student, mobile, enquiry no., staff"
        aria-label="Search visits"
        class="fi-crm-input block min-h-11 w-full ps-10"
      />
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
      @foreach (['all' => 'All visits', 'prospect' => 'Lead visits', 'enrolled' => 'Student visits'] as $value => $label)
        <button
          type="button"
          wire:click="$set('enrollmentFilter', '{{ $value }}')"
          @class(['crm-chip', 'crm-chip--active' => $enrollmentFilter === $value])
        >
          {{ $label }}
        </button>
      @endforeach
    </div>

    <details class="group mt-3" @if ($periodPreset === 'custom') open @endif>
      <summary class="flex min-h-9 cursor-pointer touch-manipulation list-none items-center justify-between gap-2 rounded-lg text-xs font-semibold text-gray-600 [&::-webkit-details-marker]:hidden dark:text-gray-300">
        <span>Custom date range</span>
        <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
      </summary>

      <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <label class="block">
          <span class="text-[10px] font-semibold uppercase tracking-[0.06em] text-gray-500 dark:text-gray-400">From</span>
          <input type="date" wire:model.live="dateFrom" class="fi-crm-input mt-1 block min-h-11 w-full" />
        </label>
        <label class="block">
          <span class="text-[10px] font-semibold uppercase tracking-[0.06em] text-gray-500 dark:text-gray-400">To</span>
          <input type="date" wire:model.live="dateTo" class="fi-crm-input mt-1 block min-h-11 w-full" />
        </label>
      </div>
    </details>

    <p class="mt-3 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
      <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500" aria-hidden="true"></span>
      <span>Showing <strong class="font-semibold text-gray-700 dark:text-gray-200">{{ $periodLabel }}</strong></span>
    </p>
  </div>

  <x-crm.stat-tiles :stats="[
    ['label' => 'Total visits', 'value' => $stats['total_visits'] ?? 0, 'tone' => 'primary'],
    ['label' => 'Unique students', 'value' => $stats['unique_students'] ?? 0, 'tone' => 'primary'],
    ['label' => 'Lead visits', 'value' => $stats['prospect_visits'] ?? 0, 'tone' => 'info'],
    ['label' => 'Student visits', 'value' => $stats['enrolled_visits'] ?? 0, 'tone' => 'success'],
    ['label' => 'First-time visitors', 'value' => $stats['first_time_visitors'] ?? 0, 'tone' => 'info'],
    ['label' => 'Visited 2+ times', 'value' => $stats['repeat_visit_students'] ?? 0, 'tone' => 'warning'],
  ]" />

  @if ($visits->isEmpty())
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35M20.25 6.32 12 2.25 3.75 6.32m0 3.03V21M9 12.75h1.5m-1.5 3h1.5" />
        </svg>
      </div>
      <p class="mt-3 text-base font-semibold text-gray-950 dark:text-white">No visits in this period</p>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Change the date range or filters above.</p>
    </div>
  @else
    <div class="flex items-center justify-between px-1 lg:px-0">
      <p class="text-[11px] font-semibold uppercase tracking-[0.06em] text-gray-500 dark:text-gray-400">
        {{ trans_choice(':count visit|:count visits', $visits->total(), ['count' => $visits->total()]) }}
      </p>
      <p class="text-[11px] text-gray-400 dark:text-gray-500">Newest first</p>
    </div>

    <div class="fi-section overflow-hidden rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
      <x-crm.responsive-table>
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
                $student = $visit->student;
                $isEnrolled = $student?->activeEnrollment !== null;
              @endphp
              <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-950 dark:text-white" data-label="Date">
                  {{ $visit->visit_date?->format('d M Y') ?? '—' }}
                </td>
                <td class="crm-responsive-table__title px-4 py-3" data-label="">
                  <div class="flex items-start gap-3">
                    <x-crm.avatar :name="$student?->name" class="lg:hidden" />

                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $student?->name ?? '—' }}</p>
                      <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $student?->mobile ?? '—' }}</p>
                    </div>

                    <x-crm.badge :tone="$isEnrolled ? 'success' : 'info'" class="lg:hidden">
                      {{ $isEnrolled ? 'Enrolled' : 'Lead' }}
                    </x-crm.badge>
                  </div>
                </td>
                <td class="hidden px-4 py-3 lg:table-cell" data-label="Type">
                  <x-crm.badge :tone="$isEnrolled ? 'success' : 'info'">
                    {{ $isEnrolled ? 'Enrolled' : 'Lead' }}
                  </x-crm.badge>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300" data-label="Course">
                  {{ $visit->enquiry?->course?->name ?? 'Not decided' }}
                </td>
                <td class="px-4 py-3" data-label="Status">
                  @if ($visit->status)
                    <x-crm.badge :tone="$visit->status->tone()">{{ $visit->status->label() }}</x-crm.badge>
                  @else
                    <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                  @endif
                </td>
                <td class="px-4 py-3 text-sm font-medium text-gray-950 dark:text-white" data-label="Logged by">
                  {{ $visit->staff?->name ?? '—' }}
                </td>
                <td class="crm-responsive-table__actions whitespace-nowrap px-4 py-3" data-label="">
                  <div class="flex items-center gap-2 lg:justify-end">
                    @if (filled($student?->mobile))
                      <a
                        href="tel:{{ $student->mobile }}"
                        class="inline-flex min-h-11 flex-1 touch-manipulation items-center justify-center gap-1.5 rounded-xl bg-gray-100 px-3 text-sm font-semibold text-gray-700 transition active:scale-[0.98] hover:bg-gray-200 lg:hidden dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.28 6.72 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.37c0-.52-.35-.96-.85-1.09l-4.42-1.1a1.12 1.12 0 0 0-1.17.4l-.97 1.29a.9.9 0 0 1-1.1.3 12.04 12.04 0 0 1-5.7-5.7.9.9 0 0 1 .3-1.1l1.29-.97c.36-.27.52-.74.4-1.17l-1.1-4.42a1.12 1.12 0 0 0-1.09-.85H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                        </svg>
                        Call
                      </a>
                    @endif

                    @if ($visit->student_id)
                      <a
                        href="{{ StudentProfilePage::getUrl(['record' => $visit->student_id]) }}"
                        class="inline-flex min-h-11 flex-1 touch-manipulation items-center justify-center gap-1.5 rounded-xl bg-primary-600 px-3 text-sm font-semibold text-white shadow-sm transition active:scale-[0.98] hover:bg-primary-500 lg:min-h-0 lg:flex-none lg:rounded-lg lg:bg-primary-50 lg:py-1.5 lg:text-xs lg:text-primary-700 lg:shadow-none lg:ring-1 lg:ring-primary-200 lg:hover:bg-primary-100 dark:lg:bg-primary-500/10 dark:lg:text-primary-300 dark:lg:ring-primary-500/30"
                      >
                        Profile
                        <svg class="h-4 w-4 lg:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                      </a>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </x-crm.responsive-table>
    </div>

    <div class="pt-2">
      {{ $visits->links() }}
    </div>
  @endif
</div>
