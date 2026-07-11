{{-- Keep poll outside the heavy dashboard markup so Filament schema re-renders do not drop it. --}}
@if (($viewMode ?? 'live') === 'live')
    <div
        wire:poll.30s.keep-alive="pollLiveDashboard"
        wire:key="attendance-live-poll"
        class="hidden"
        aria-hidden="true"
    ></div>
@endif
