@php
    $appName = \App\Support\InstituteSettings::brandName().' Admin';
@endphp

<x-pwa.head context="admin" :app-name="$appName" />

@if (file_exists(public_path('build/manifest.json')))
    @vite('resources/js/filament-pwa.js')
@endif
