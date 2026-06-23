@php
    use App\Support\MeetingForOptions;

    $counts = array_filter($leadSources['meeting_for_counts'] ?? [], fn (int $count): bool => $count > 0);
@endphp

@if ($counts !== [])
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($counts as $value => $count)
            @include('filament.pages.partials.meeting-for-badge', [
                'value' => (string) $value,
                'size' => 'md',
            ])
            @if ($count > 1)
                @php($style = MeetingForOptions::badgeStyle((string) $value))
                <span @class(['text-xs font-bold', $style['text']])>×{{ $count }}</span>
            @endif
        @endforeach
    </div>
@endif
