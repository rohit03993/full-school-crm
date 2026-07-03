@php
    $appName = \App\Support\InstituteSettings::brandName().' Admin';
    $viteManifestPath = public_path('build/manifest.json');
    $canLoadPwaJs = false;

    if (is_file($viteManifestPath)) {
        $manifest = json_decode((string) file_get_contents($viteManifestPath), true);
        $canLoadPwaJs = is_array($manifest) && array_key_exists('resources/js/filament-pwa.js', $manifest);
    }
@endphp

<x-pwa.head context="admin" :app-name="$appName" />

@if ($canLoadPwaJs)
    @vite('resources/js/filament-pwa.js')
@endif
