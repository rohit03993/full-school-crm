@if (file_exists(public_path('build/manifest.json')))
    @vite($assets)
@else
    {{-- Server deploy without `npm run build` — avoid Vite 500; run npm ci && npm run build for full styling --}}
@endif
