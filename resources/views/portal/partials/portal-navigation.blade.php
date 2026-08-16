@php
    $onHomework = request()->routeIs('portal.homework.*');
    $onDashboard = request()->routeIs('portal.dashboard');
    $badge = (int) ($portalNav['homeworkBadge'] ?? 0);
    $showAdmission = (bool) ($portalNav['hasAdmission'] ?? false);
    $showHomework = (bool) ($portalNav['showHomework'] ?? true);
    $showFees = (bool) ($portalNav['showFees'] ?? true);
    $showMarks = (bool) ($portalNav['showMarks'] ?? true);
    $showAttendance = (bool) ($portalNav['showAttendance'] ?? false);
    $linkedChildren = $portalNav['linkedChildren'] ?? [];
    $activeStudentId = $portalNav['activeStudentId'] ?? null;

    $primaryNav = [
        ['key' => 'home', 'label' => 'Overview', 'href' => route('portal.dashboard'), 'route' => 'home', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ];

    if ($showHomework) {
        $primaryNav[] = ['key' => 'homework', 'label' => 'Homework', 'href' => route('portal.homework.index'), 'route' => 'homework', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'badge' => $badge];
    }

    if ($showFees) {
        $primaryNav[] = ['key' => 'fees', 'label' => 'Fees', 'href' => route('portal.dashboard').'#fees', 'route' => 'fees', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'];
    }

    if ($showAttendance) {
        $primaryNav[] = ['key' => 'attendance', 'label' => 'Attendance', 'href' => route('portal.dashboard').'#attendance', 'route' => 'attendance', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'];
    }

    if ($showMarks) {
        $primaryNav[] = ['key' => 'marks', 'label' => 'Marks', 'href' => route('portal.dashboard').'#marks', 'route' => 'marks', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'];
    }

    if ($showAdmission) {
        $primaryNav[] = ['key' => 'admission', 'label' => 'Admission', 'href' => route('portal.dashboard').'#admission', 'route' => 'admission', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'sidebarOnly' => true];
    }

    $primaryNav[] = ['key' => 'more', 'label' => 'Account', 'href' => route('portal.dashboard').'#more', 'route' => 'more', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'];

    // Bottom bar keeps 5 items max for thumbs; drop Marks to sidebar/tabs when crowded.
    $mobileNav = array_values(array_filter($primaryNav, fn (array $item): bool => empty($item['sidebarOnly'])));
    if (count($mobileNav) > 5) {
        $mobileNav = array_values(array_filter($mobileNav, fn (array $item): bool => $item['route'] !== 'marks'));
    }

    $resolveActive = function (string $route) use ($onHomework, $onDashboard): bool {
        if ($onHomework) {
            return $route === 'homework';
        }

        return $onDashboard && $route === 'home';
    };
@endphp

{{-- Desktop sidebar --}}
<aside
    id="portal-sidebar-nav"
    class="portal-sidebar hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-50 lg:flex lg:w-64 lg:flex-col lg:border-r lg:border-navy-100 lg:bg-white"
    aria-label="Parent and student portal navigation"
>
    <div class="border-b border-navy-100 px-5 py-5">
        @if (! empty($institute['logo_url']))
            <img
                src="{{ $institute['logo_url'] }}"
                alt=""
                class="mb-3 object-contain {{ \App\Support\SiteLogo::isSquare($institute['logo_shape'] ?? null) ? 'h-11 w-11' : 'h-9 w-auto' }}"
            >
        @endif
        <p class="text-[11px] font-bold uppercase tracking-wider text-brand-600">{{ $institute['name'] ?? config('app.name') }}</p>
        <p class="mt-0.5 font-display text-lg font-bold text-navy-900">Parent &amp; Student</p>
    </div>

    @if (! empty($portalNav['student']))
        @php $navStudent = $portalNav['student']; @endphp
        <div class="border-b border-navy-100 px-4 py-4">
            <div class="flex items-center gap-3 rounded-2xl bg-navy-50 p-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-xs font-bold text-navy-950">
                    {{ $navStudent['initials'] }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-navy-900">{{ $navStudent['name'] }}</p>
                    <p class="truncate text-xs text-navy-500">{{ $navStudent['subtitle'] }}</p>
                </div>
            </div>

            @if (count($linkedChildren) > 1)
                <div class="mt-3 space-y-1.5">
                    <p class="px-1 text-[10px] font-bold uppercase tracking-wider text-navy-400">Switch child</p>
                    @foreach ($linkedChildren as $child)
                        @if ((int) $child['id'] === (int) $activeStudentId)
                            <div class="rounded-xl bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-800 ring-1 ring-brand-200">
                                {{ $child['name'] }} · viewing
                            </div>
                        @else
                            <form method="POST" action="{{ route('portal.switch-child') }}">
                                @csrf
                                <input type="hidden" name="student_id" value="{{ $child['id'] }}">
                                <button type="submit" class="w-full rounded-xl px-3 py-2 text-left text-xs font-semibold text-navy-700 transition hover:bg-navy-50">
                                    {{ $child['name'] }}
                                    @if (! empty($child['subtitle']))
                                        <span class="mt-0.5 block font-normal text-navy-400">{{ $child['subtitle'] }}</span>
                                    @endif
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        <ul class="space-y-1">
            @foreach ($primaryNav as $item)
                @php $isActive = $resolveActive($item['route']); @endphp
                <li>
                    <a
                        href="{{ $item['href'] }}"
                        data-portal-nav="{{ $item['route'] }}"
                        data-portal-nav-context="sidebar"
                        @class([
                            'portal-sidebar-link',
                            'portal-sidebar-link--active' => $isActive,
                        ])
                        @if ($isActive) aria-current="page" @endif
                    >
                        <span class="relative flex h-5 w-5 shrink-0 items-center justify-center">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            @if (! empty($item['badge']))
                                <span class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white">
                                    {{ $item['badge'] > 9 ? '9+' : $item['badge'] }}
                                </span>
                            @endif
                        </span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="border-t border-navy-100 p-4 space-y-2">
        <x-pwa.install-button class="portal-sidebar-footer-link w-full text-left">
            Install app
        </x-pwa.install-button>
        <a href="{{ route('home') }}" class="portal-sidebar-footer-link">
            ← Back to website
        </a>
        <form method="POST" action="{{ route('portal.logout') }}">
            @csrf
            <button type="submit" class="portal-sidebar-footer-link w-full text-left">
                Logout
            </button>
        </form>
    </div>
</aside>

{{-- Mobile bottom bar --}}
<nav
    id="portal-bottom-nav"
    class="portal-bottom-nav fixed inset-x-0 bottom-0 z-50 border-t border-navy-100/90 bg-white/95 shadow-[0_-4px_24px_rgba(16,42,67,0.08)] backdrop-blur-md supports-[backdrop-filter]:bg-white/90 lg:hidden"
    aria-label="Parent and student portal navigation"
>
    <div class="mx-auto flex max-w-lg items-stretch justify-around gap-0.5 px-1 pt-1.5">
        @foreach ($mobileNav as $item)
            @php
                $mobileRoute = $item['route'] === 'more' ? 'more' : $item['route'];
                $mobileLabel = $item['route'] === 'home' ? 'Home' : ($item['route'] === 'more' ? 'More' : $item['label']);
                $isActive = $resolveActive($mobileRoute === 'home' ? 'home' : $item['route']);
            @endphp
            <a
                href="{{ $item['href'] }}"
                data-portal-nav="{{ $item['route'] }}"
                data-portal-nav-context="bottom"
                @class([
                    'portal-nav-item touch-manipulation relative flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-2 text-[10px] font-semibold transition',
                    'text-brand-700' => $isActive,
                    'text-navy-500 hover:text-navy-800' => ! $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                <span class="relative flex h-6 w-6 items-center justify-center">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    @if (! empty($item['badge']))
                        <span class="absolute -right-1.5 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white">
                            {{ $item['badge'] > 9 ? '9+' : $item['badge'] }}
                        </span>
                    @endif
                </span>
                <span class="truncate">{{ $mobileLabel }}</span>
                @if ($isActive)
                    <span data-nav-indicator class="absolute inset-x-3 bottom-0.5 h-0.5 rounded-full bg-brand-500" aria-hidden="true"></span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
