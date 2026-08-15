@php
    use App\Services\PwaManifestService;
    use App\Support\ViteManifest;

    $appName = PwaManifestService::displayName('admin');
@endphp

<x-pwa.head context="admin" :app-name="$appName" />

@if (ViteManifest::hasEntry('resources/js/filament-pwa.js'))
    @vite('resources/js/filament-pwa.js')
@endif
