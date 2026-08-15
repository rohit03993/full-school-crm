@php
    $name = $institute['name'] ?? config('institute.name');
    $tagline = $institute['tagline'] ?? '';
    $logoUrl = $institute['logo_url'] ?? null;
    $faviconUrl = $institute['favicon_url'] ?? null;
    $theme = \App\Services\PwaManifestService::themeColor();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $theme }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $name }}</title>
    <x-pwa.head />
    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}"@if (! empty($institute['favicon_type'])) type="{{ $institute['favicon_type'] }}"@endif>
    @else
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:600,700" rel="stylesheet">
    @include('partials.vite-assets', ['assets' => ['resources/css/app.css', 'resources/js/app.js']])
</head>
<body class="min-h-screen bg-navy-950 text-white" style="padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);">
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-10">
        <div class="text-center">
            @if ($faviconUrl || $logoUrl)
                <img
                    src="{{ $faviconUrl ?: $logoUrl }}"
                    alt="{{ $name }}"
                    class="mx-auto mb-5 h-20 w-20 object-contain"
                >
            @endif
            <h1 class="font-display text-3xl font-bold tracking-tight">{{ $name }}</h1>
            @if ($tagline)
                <p class="mt-2 text-sm text-navy-300">{{ $tagline }}</p>
            @endif
            <p class="mt-6 text-sm leading-relaxed text-navy-200">
                One app for this institute. Sign in and we open the right area for you.
            </p>
        </div>

        <div class="mt-10 space-y-3">
            <a
                href="{{ url('/admin/login') }}"
                class="flex min-h-[52px] items-center justify-center rounded-2xl bg-brand-500 px-5 text-base font-bold text-navy-950 touch-manipulation active:bg-brand-400"
            >
                Staff / Admin
            </a>

            @if ($portalAvailable)
                <a
                    href="{{ route('portal.login') }}"
                    class="flex min-h-[52px] items-center justify-center rounded-2xl border border-white/20 bg-white/5 px-5 text-base font-semibold text-white touch-manipulation active:bg-white/10"
                >
                    Parent / Student
                </a>
            @endif

            <a
                href="{{ route('home') }}"
                class="flex min-h-[48px] items-center justify-center px-5 text-sm font-medium text-navy-300 touch-manipulation hover:text-white"
            >
                Visit website
            </a>
        </div>
    </div>
    <x-pwa.install-prompt />
</body>
</html>
