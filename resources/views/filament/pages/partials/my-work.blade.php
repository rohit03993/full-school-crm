@php
    use App\Filament\Pages\MyLeadsPage;
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-4xl lg:pb-6">
  <div class="flex flex-wrap gap-2">
    <button
      type="button"
      wire:click="switchWorkTab('meetings')"
      @class([
        'rounded-full px-3 py-1.5 text-xs font-semibold transition touch-manipulation',
        'bg-primary-600 text-white shadow-sm' => $workTab === 'meetings',
        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/15' => $workTab !== 'meetings',
      ])
    >
      Meetings
      @if (($stats['open'] ?? 0) > 0)
        <span class="ml-1 rounded-full bg-white/20 px-1.5 py-0.5 text-[10px]">{{ $stats['open'] }}</span>
      @endif
    </button>

    @if ($canMyCasesTab)
      <button
        type="button"
        wire:click="switchWorkTab('my_cases')"
        @class([
          'rounded-full px-3 py-1.5 text-xs font-semibold transition touch-manipulation',
          'bg-primary-600 text-white shadow-sm' => $workTab === 'my_cases',
          'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/15' => $workTab !== 'my_cases',
        ])
      >
        My cases
        @if (($caseStats['open'] ?? 0) > 0)
          <span class="ml-1 rounded-full bg-white/20 px-1.5 py-0.5 text-[10px]">{{ $caseStats['open'] }}</span>
        @endif
      </button>
    @endif
  </div>

  @if ($workTab === 'meetings')
    @if (($callStats['uncalled'] ?? 0) > 0 || ($callStats['due_call_followups'] ?? 0) > 0)
      <a
        href="{{ MyLeadsPage::getUrl() }}"
        class="flex items-center justify-between gap-3 rounded-xl border border-primary-200/80 bg-primary-50/80 px-4 py-3 shadow-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-primary-500/25 dark:bg-primary-500/10"
      >
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-primary-800 dark:text-primary-300">Assigned to call</p>
          <p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">
            {{ $callStats['uncalled'] ?? 0 }} uncalled lead{{ ($callStats['uncalled'] ?? 0) === 1 ? '' : 's' }}
            @if (($callStats['due_call_followups'] ?? 0) > 0)
              · {{ $callStats['due_call_followups'] }} due follow-up{{ ($callStats['due_call_followups'] ?? 0) === 1 ? '' : 's' }}
            @endif
          </p>
        </div>
        <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
      </a>
    @endif

    @include('filament.pages.partials.my-meetings-list', [
      'meetings' => $meetings,
      'search' => $search,
      'statusFilter' => $statusFilter,
      'stats' => $stats,
    ])
  @else
    @include('filament.pages.partials.my-cases', [
      'cases' => $myCases,
      'caseTypeOptions' => $caseTypeOptions,
      'stats' => $caseStats,
      'embedded' => true,
    ])
  @endif
</div>
