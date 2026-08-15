@props([
    'context' => 'public',
    'appName' => null,
])

@php
    $appName ??= \App\Services\PwaManifestService::displayName($context);
    $shortName = \App\Services\PwaManifestService::shortName($context);
    $themeColor = \App\Services\PwaManifestService::themeColor();
    $manifestUrl = url('/pwa/manifest/'.$context);
    $icon192 = url('/pwa/icon/192');
    $icon512 = url('/pwa/icon/512');
@endphp

<meta name="crm-pwa-context" content="{{ $context }}">
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
