@php
    use App\Support\ViteManifest;

    $entries = is_array($assets ?? null) ? $assets : [];
    $canLoad = $entries !== [] && ViteManifest::hasEntries($entries);
@endphp

@if ($canLoad)
    @vite($entries)
@endif
