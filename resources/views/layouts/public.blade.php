<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="{{ $metaDescription ?? ($institute['tagline'] ?? '') }}">
    <meta name="theme-color" content="#102a43">
    <meta name="mobile-web-app-capable" content="yes">

    <title>{{ isset($title) ? $title.' — ' : '' }}{{ $institute['name'] ?? config('institute.name') }}</title>

    @if (! empty($institute['favicon_url']))
        <link rel="icon" href="{{ $institute['favicon_url'] }}" type="image/png">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:500,600,700" rel="stylesheet">

    @include('partials.vite-assets', ['assets' => ['resources/css/app.css', 'resources/js/app.js']])
</head>
<body class="flex min-h-screen flex-col overflow-x-hidden bg-white pb-[calc(52px+env(safe-area-inset-bottom))] lg:pb-0">
    @include('components.public.header')

    <main class="flex-1">
        @yield('content')
    </main>

    @include('components.public.footer')
    @include('components.public.mobile-action-bar')
</body>
</html>
