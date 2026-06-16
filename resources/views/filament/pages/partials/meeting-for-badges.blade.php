@php
    $folksIndiaCount = $leadSources['folks_india_count'] ?? 0;
    $englishCoffeeCount = $leadSources['english_coffee_count'] ?? 0;
@endphp

@if ($folksIndiaCount > 0 || $englishCoffeeCount > 0)
    <div class="flex flex-wrap items-center gap-2">
        @if ($folksIndiaCount > 0)
            @include('filament.pages.partials.meeting-for-badge', [
                'meetingFor' => \App\Enums\MeetingFor::FolksIndia,
                'size' => 'md',
            ])
            @if ($folksIndiaCount > 1)
                <span class="text-xs font-bold text-amber-700 dark:text-amber-400">×{{ $folksIndiaCount }}</span>
            @endif
        @endif

        @if ($englishCoffeeCount > 0)
            @include('filament.pages.partials.meeting-for-badge', [
                'meetingFor' => \App\Enums\MeetingFor::EnglishCoffee,
                'size' => 'md',
            ])
            @if ($englishCoffeeCount > 1)
                <span class="text-xs font-bold text-violet-700 dark:text-violet-400">×{{ $englishCoffeeCount }}</span>
            @endif
        @endif
    </div>
@endif
