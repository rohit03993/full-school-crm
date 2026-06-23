<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#102a43">
    <title>@yield('title', 'Student Portal') — {{ $institute['name'] ?? config('app.name') }}</title>

    @if (! empty($institute['favicon_url']))
        <link rel="icon" href="{{ $institute['favicon_url'] }}" type="image/png">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:500,600,700" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-navy-50 text-navy-900 antialiased pb-[calc(1rem+env(safe-area-inset-bottom))]">
    <header class="sticky top-0 z-50 border-b border-navy-100/80 bg-white/95 shadow-sm backdrop-blur-md supports-[backdrop-filter]:bg-white/80">
        <div class="mx-auto flex max-w-lg items-center gap-3 px-4 py-3 sm:max-w-2xl lg:max-w-3xl">
            @hasSection('avatar')
                @yield('avatar')
            @endif

            <div class="min-w-0 flex-1">
                <p class="truncate text-[11px] font-bold uppercase tracking-wider text-brand-600">
                    @yield('eyebrow', 'Student Portal')
                </p>
                <h1 class="truncate font-display text-lg font-bold leading-tight text-navy-900 sm:text-xl">
                    @yield('heading')
                </h1>
                @hasSection('subheading')
                    <p class="truncate text-xs text-navy-500 sm:text-sm">@yield('subheading')</p>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @yield('header_actions')
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-lg px-4 py-5 sm:max-w-2xl sm:py-6 lg:max-w-3xl">
        @if (session('portal_success'))
            <div class="mb-5 flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-white">✓</span>
                <p>{{ session('portal_success') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-lg px-4 pb-6 text-center sm:max-w-2xl lg:max-w-3xl">
        <a href="{{ route('home') }}" class="text-sm font-medium text-navy-500 transition hover:text-navy-800">
            ← Back to {{ $institute['name'] ?? 'website' }}
        </a>
    </footer>

    @stack('scripts')
</body>
</html>
