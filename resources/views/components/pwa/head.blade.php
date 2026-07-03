@props([
    'context' => 'public',
    'appName' => null,
])

@php
    $appName ??= \App\Support\InstituteSettings::brandName();
    $manifestUrl = route('pwa.manifest', ['context' => $context]);
    $icon192 = route('pwa.icon', ['size' => 192]);
@endphp

<meta name="crm-pwa-context" content="{{ $context }}">
<meta name="crm-pwa-app-name" content="{{ $appName }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="manifest" href="{{ $manifestUrl }}">
<link rel="apple-touch-icon" href="{{ $icon192 }}">
