@props([
    'context' => 'app',
    'appName' => null,
])

@php
    // One institute app everywhere — context is kept only for legacy callers.
    $appName ??= \App\Services\PwaManifestService::displayName();
    $shortName = \App\Services\PwaManifestService::shortName();
    $themeColor = \App\Services\PwaManifestService::themeColor();
    $manifestUrl = url('/pwa/manifest');
    $icon192 = \App\Services\PwaManifestService::iconUrl(192);
    $icon512 = \App\Services\PwaManifestService::iconUrl(512);
@endphp

<meta name="crm-pwa-context" content="app">
<meta name="crm-pwa-app-name" content="{{ $appName }}">
<meta name="theme-color" content="{{ $themeColor }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $shortName }}">
<link rel="manifest" href="{{ $manifestUrl }}">
<link rel="apple-touch-icon" href="{{ $icon192 }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ $icon192 }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ $icon512 }}">
