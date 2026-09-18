@php
    $summary = $summary ?? 'This month';
    $isCustom = (bool) ($isCustom ?? false);
@endphp

<button
    type="button"
    class="crm-dash-filters__toggle"
    x-on:click="expanded = !expanded"
    x-bind:aria-expanded="expanded.toString()"
    x-init="if (@js($isCustom)) expanded = true"
>
    <span class="crm-dash-filters__toggle-copy">
        <span class="crm-dash-filters__toggle-kicker">Session &amp; class</span>
        <span class="crm-dash-filters__toggle-summary">{{ $summary }}</span>
    </span>
    <span class="crm-dash-filters__toggle-icon" aria-hidden="true">
        <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4" />
    </span>
</button>
