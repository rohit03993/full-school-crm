@props([
    'label',
    'for' => null,
    'required' => false,
    'hint' => null,
    'disabled' => false,
])

@php
    $selectAttributes = $attributes
        ->except(['class', 'label', 'for', 'required', 'hint', 'disabled'])
        ->class(['fi-crm-select']);

    if (filled($for)) {
        $selectAttributes = $selectAttributes->merge(['id' => $for]);
    }
@endphp

<x-crm.field :label="$label" :for="$for" :required="$required" class="fi-crm-field">
    <select @disabled($disabled) {{ $selectAttributes }}>
        {{ $slot }}
    </select>

    @if (filled($hint))
        <p class="fi-crm-hint mt-1.5">{{ $hint }}</p>
    @endif
</x-crm.field>
