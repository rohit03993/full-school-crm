@php
    use App\Enums\LicenseFeature;
    use App\Filament\Pages\CallQueuePage;
    use App\Filament\Pages\Dashboard;
    use App\Filament\Pages\FollowUpsPage;
    use App\Filament\Pages\StudentSearchPage;
    use App\Filament\Resources\Enquiries\EnquiryResource;
    use App\Support\CrmNavBadges;
    use App\Support\FeatureGate;

    $dueCount = CrmNavBadges::followUpsDue();
    $currentPath = trim(request()->path(), '/');

    $tabs = [
        [
            'label' => 'Home',
            'url' => Dashboard::getUrl(),
            'icon' => 'heroicon-o-home',
            'active' => $currentPath === 'admin' || str_ends_with($currentPath, '/admin'),
            'visible' => true,
        ],
        [
            'label' => 'Search',
            'url' => StudentSearchPage::getUrl(),
            'icon' => 'heroicon-o-magnifying-glass',
            'active' => str_contains($currentPath, 'student-search-page'),
            'visible' => true,
        ],
        [
            'label' => 'Leads',
            'url' => EnquiryResource::getUrl('index'),
            'icon' => 'heroicon-o-inbox-stack',
            'active' => str_contains($currentPath, 'enquiries'),
            'visible' => FeatureGate::enabled(LicenseFeature::Enquiries),
        ],
        [
            'label' => 'Follow-ups',
            'url' => FollowUpsPage::getUrl(),
            'icon' => 'heroicon-o-bell-alert',
            'active' => str_contains($currentPath, 'follow-ups-page'),
            'badge' => $dueCount > 0 ? $dueCount : null,
            'visible' => FeatureGate::enabled(LicenseFeature::Enquiries),
        ],
        [
            'label' => 'Calls',
            'url' => CallQueuePage::getUrl(),
            'icon' => 'heroicon-o-phone',
            'active' => str_contains($currentPath, 'call-queue-page'),
            'visible' => FeatureGate::enabled(LicenseFeature::Calls),
        ],
    ];

    $tabs = array_values(array_filter($tabs, fn (array $tab): bool => $tab['visible']));
@endphp

<div class="fi-mobile-bottom-nav lg:hidden">
    <div class="h-[4.25rem] pb-[env(safe-area-inset-bottom)]" aria-hidden="true"></div>

    <nav
        class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] backdrop-blur-md dark:border-white/10 dark:bg-gray-900/95"
        aria-label="Quick navigation"
    >
        <div class="mx-auto flex h-[4.25rem] max-w-lg items-stretch justify-around px-0.5 pb-[env(safe-area-inset-bottom)]">
            @foreach ($tabs as $tab)
                <a
                    href="{{ $tab['url'] }}"
                    wire:navigate
                    @class([
                        'relative flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-lg px-0.5 transition touch-manipulation active:scale-95',
                        'text-primary-600 dark:text-primary-400' => $tab['active'],
                        'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => ! $tab['active'],
                    ])
                >
                    <span @class([
                        'rounded-xl p-1.5 transition',
                        'bg-primary-500/10 ring-1 ring-primary-500/20' => $tab['active'],
                    ])>
                        <x-filament::icon :icon="$tab['icon']" class="h-5 w-5" />
                    </span>
                    <span class="w-full truncate text-center text-[10px] font-semibold leading-tight">{{ $tab['label'] }}</span>
                    @if (($tab['badge'] ?? null) > 0)
                        <span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-warning-500 px-1 text-[9px] font-bold text-white">
                            {{ $tab['badge'] > 9 ? '9+' : $tab['badge'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>
</div>
