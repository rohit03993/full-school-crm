<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#102a43">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>@yield('title', 'Student Portal') — {{ $institute['name'] ?? config('app.name') }}</title>

    @if (! empty($institute['favicon_url']))
        <link rel="icon" href="{{ $institute['favicon_url'] }}" type="image/png">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:500,600,700" rel="stylesheet">

    @include('partials.vite-assets', ['assets' => ['resources/css/app.css', 'resources/js/app.js']])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="min-h-screen bg-navy-50 text-navy-900 antialiased pb-portal-nav lg:pb-0">
    @if (! $__env->hasSection('hide_bottom_nav'))
        @include('portal.partials.portal-navigation')
    @endif

    <div class="lg:pl-64">
        <header class="sticky top-0 z-40 border-b border-navy-100/80 bg-white/95 shadow-sm backdrop-blur-md supports-[backdrop-filter]:bg-white/80 lg:hidden">
            <div class="mx-auto flex max-w-lg items-center gap-3 px-4 py-3">
                @hasSection('avatar')
                    @yield('avatar')
                @endif

                <div class="min-w-0 flex-1">
                    <p class="truncate text-[11px] font-bold uppercase tracking-wider text-brand-600">
                        @yield('eyebrow', 'Student Portal')
                    </p>
                    <h1 class="truncate font-display text-lg font-bold leading-tight text-navy-900">
                        @yield('heading')
                    </h1>
                    @hasSection('subheading')
                        <p class="truncate text-xs text-navy-500">@yield('subheading')</p>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @yield('header_actions')
                </div>
            </div>
        </header>

        <header class="hidden border-b border-navy-100 bg-white px-8 py-6 lg:block">
            <div class="mx-auto max-w-5xl">
                <p class="text-[11px] font-bold uppercase tracking-wider text-brand-600">
                    @yield('eyebrow', 'Student Portal')
                </p>
                <h1 class="mt-1 font-display text-2xl font-bold text-navy-900">
                    @yield('heading')
                </h1>
                @hasSection('subheading')
                    <p class="mt-1 text-sm text-navy-500">@yield('subheading')</p>
                @endif
            </div>
        </header>

        <main class="mx-auto max-w-lg px-4 py-4 sm:max-w-2xl sm:py-5 lg:max-w-5xl lg:px-8 lg:py-8">
            @if (session('portal_success'))
                <div class="mb-4 flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-white">✓</span>
                    <p>{{ session('portal_success') }}</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('scripts')

    <script>
        (function () {
            const dashboardPath = @json(parse_url(route('portal.dashboard'), PHP_URL_PATH));
            const validTabs = ['home', 'fees', 'marks', 'admission', 'more'];

            function currentTab() {
                const hash = window.location.hash.replace('#', '');
                return validTabs.includes(hash) ? hash : 'home';
            }

            function setActive() {
                const path = window.location.pathname;
                const onHomework = path.includes('/homework');
                const onDashboard = path === dashboardPath || path === dashboardPath + '/';
                const tab = onDashboard ? currentTab() : null;

                document.querySelectorAll('[data-portal-nav]').forEach(function (link) {
                    const key = link.getAttribute('data-portal-nav');
                    const context = link.getAttribute('data-portal-nav-context');
                    let isActive = false;

                    if (onHomework) {
                        isActive = key === 'homework';
                    } else if (onDashboard) {
                        if (context === 'bottom') {
                            isActive = key === tab || (tab === 'admission' && key === 'more');
                        } else {
                            isActive = key === tab;
                        }
                    }

                    link.classList.toggle('text-brand-700', isActive && context === 'bottom');
                    link.classList.toggle('text-navy-500', ! isActive && context === 'bottom');
                    link.classList.toggle('portal-sidebar-link--active', isActive && context === 'sidebar');
                    link.toggleAttribute('aria-current', isActive ? 'page' : false);

                    if (context !== 'bottom') {
                        return;
                    }

                    let indicator = link.querySelector('[data-nav-indicator]');
                    if (isActive && ! indicator) {
                        indicator = document.createElement('span');
                        indicator.setAttribute('data-nav-indicator', '');
                        indicator.className = 'absolute inset-x-3 bottom-0.5 h-0.5 rounded-full bg-brand-500';
                        indicator.setAttribute('aria-hidden', 'true');
                        link.appendChild(indicator);
                    } else if (! isActive && indicator) {
                        indicator.remove();
                    }
                });
            }

            window.addEventListener('hashchange', setActive);
            document.addEventListener('DOMContentLoaded', setActive);
            document.addEventListener('alpine:initialized', setActive);
            window.portalNavRefresh = setActive;
        })();
    </script>
</body>
</html>
