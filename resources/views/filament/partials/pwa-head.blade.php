@php
    use App\Support\ViteManifest;
@endphp

<x-pwa.head />

{{-- Filament's own viewport tag has no viewport-fit=cover, so env(safe-area-inset-*) stays 0 on a phone. --}}
<script>
    document.querySelector('meta[name="viewport"]')?.setAttribute(
        'content',
        'width=device-width, initial-scale=1, viewport-fit=cover'
    );
</script>

@if (ViteManifest::hasEntry('resources/js/filament-pwa.js'))
    @vite('resources/js/filament-pwa.js')
@endif
