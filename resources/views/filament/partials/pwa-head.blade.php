@php
    use App\Support\ViteManifest;

    $appName = \App\Support\InstituteSettings::brandName().' Admin';
@endphp

<x-pwa.head context="admin" :app-name="$appName" />

@if (ViteManifest::hasEntry('resources/js/filament-pwa.js'))
    @vite('resources/js/filament-pwa.js')
@endif
