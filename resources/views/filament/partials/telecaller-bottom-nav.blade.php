@php
    use App\Filament\Pages\CallQueuePage;
    use App\Filament\Pages\CallReportPage;
    use App\Filament\Pages\Dashboard;
    use App\Filament\Pages\FollowUpsPage;
    use App\Filament\Pages\MyLeadsPage;
    use App\Services\FollowUpWorklistService;

    $dueCount = app(FollowUpWorklistService::class)->totalDueCount();
    $currentPath = trim(request()->path(), '/');

    $tabs = [
        [
            'label' => 'Home',
            'url' => Dashboard::getUrl(),
            'icon' => 'heroicon-o-home',
            'active' => $currentPath === 'admin' || str_ends_with($currentPath, '/admin'),
        ],
        [
            'label' => 'Assigned to Call',
            'url' => MyLeadsPage::getUrl(),
            'icon' => 'heroicon-o-user-group',
            'active' => str_contains($currentPath, 'my-leads-page'),
        ],
        [
            'label' => 'Call Queue',
            'url' => CallQueuePage::getUrl(),
            'icon' => 'heroicon-o-phone',
            'active' => str_contains($currentPath, 'call-queue-page'),
        ],
        [
            'label' => 'Follow-ups',
            'url' => FollowUpsPage::getUrl(),
            'icon' => 'heroicon-o-bell-alert',
            'active' => str_contains($currentPath, 'follow-ups-page'),
            'badge' => $dueCount > 0 ? $dueCount : null,
        ],
        [
            'label' => 'Report',
            'url' => CallReportPage::getUrl(),
            'icon' => 'heroicon-o-chart-bar',
            'active' => str_contains($currentPath, 'call-report-page'),
        ],
    ];
@endphp

<div class="fi-telecaller-bottom-nav md:hidden">
    <div class="h-16" aria-hidden="true"></div>

    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white shadow-[0_-2px_10px_rgba(0,0,0,0.06)] dark:border-white/10 dark:bg-gray-900">
        <div class="mx-auto flex h-16 max-w-lg items-center justify-around px-1">
            @foreach ($tabs as $tab)
                <a
                    href="{{ $tab['url'] }}"
                    @class([
                        'relative flex flex-1 flex-col items-center justify-center rounded-lg px-1 pt-1 pb-1 transition touch-manipulation',
                        'text-primary-600 dark:text-primary-400' => $tab['active'],
                        'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300' => ! $tab['active'],
                    ])
                >
                    <span @class([
                        'rounded-xl p-1.5 transition',
                        'bg-primary-500/10 shadow-sm' => $tab['active'],
                    ])>
                        <x-filament::icon :icon="$tab['icon']" class="h-5 w-5" />
                    </span>
                    <span class="mt-0.5 text-[10px] leading-tight font-semibold">{{ $tab['label'] }}</span>
                    @if (($tab['badge'] ?? null) > 0)
                        <span class="absolute top-1 right-3 flex h-4 min-w-4 items-center justify-center rounded-full bg-warning-500 px-1 text-[9px] font-bold text-white">
                            {{ $tab['badge'] > 9 ? '9+' : $tab['badge'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>
</div>
