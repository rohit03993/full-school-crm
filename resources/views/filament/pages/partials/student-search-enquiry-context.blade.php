@php
    $lookedUpMobile = $lookedUpMobile ?? '';
@endphp

<div class="fi-student-search-enquiry-context">
    <div class="fi-student-search-enquiry-context-main">
        <p class="fi-student-search-enquiry-context-label">New number — no profile yet</p>
        <p class="fi-student-search-enquiry-context-mobile">{{ $lookedUpMobile }}</p>
        <p class="fi-student-search-enquiry-context-hint">Add name below and save — full profile can wait.</p>
    </div>
    <button
        type="button"
        wire:click="clearSearch"
        class="fi-student-search-enquiry-context-change"
    >
        <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" />
        Change number
    </button>
</div>
