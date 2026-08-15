@php
    use App\Support\ViteManifest;
@endphp

<x-pwa.head />

@if (ViteManifest::hasEntry('resources/js/filament-pwa.js'))
    @vite('resources/js/filament-pwa.js')
@endif
