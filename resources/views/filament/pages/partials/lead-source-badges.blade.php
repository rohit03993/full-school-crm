@php
    $websiteCount = $leadSources['website_count'] ?? 0;
    $walkInCount = $leadSources['walk_in_count'] ?? 0;
    $directAdmissionCount = $leadSources['direct_admission_count'] ?? 0;
    $hasAny = $websiteCount > 0 || $walkInCount > 0 || $directAdmissionCount > 0;
@endphp

@if ($hasAny)
    <div class="flex flex-wrap items-center gap-2">
        @if ($directAdmissionCount > 0)
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary-500/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-primary-800 ring-1 ring-primary-500/25 dark:text-primary-300">
                <x-filament::icon icon="heroicon-m-user-plus" class="h-4 w-4" />
                Direct admission
                @if ($directAdmissionCount > 1)
                    <span class="rounded-md bg-primary-500/20 px-1.5 py-0.5 text-[10px]">×{{ $directAdmissionCount }}</span>
                @endif
            </span>
        @endif

        @if ($websiteCount > 0)
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-500/25 dark:text-emerald-400">
                <x-filament::icon icon="heroicon-m-globe-alt" class="h-4 w-4" />
                Website
                @if ($websiteCount > 1)
                    <span class="rounded-md bg-emerald-500/20 px-1.5 py-0.5 text-[10px]">×{{ $websiteCount }}</span>
                @endif
            </span>
        @endif

        @if ($walkInCount > 0)
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-sky-500/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-sky-800 ring-1 ring-sky-500/25 dark:text-sky-400">
                <x-filament::icon icon="heroicon-m-building-storefront" class="h-4 w-4" />
                Walk-in
                @if ($walkInCount > 1)
                    <span class="rounded-md bg-sky-500/20 px-1.5 py-0.5 text-[10px]">×{{ $walkInCount }}</span>
                @endif
            </span>
        @endif
    </div>
@endif
