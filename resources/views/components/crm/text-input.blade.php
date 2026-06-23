@props([
    'label',
    'model',
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'step' => null,
])

<x-crm.field :label="$label" :required="$required" class="fi-crm-field">
    <input
        type="{{ $type }}"
        wire:model="{{ $model }}"
        placeholder="{{ $placeholder }}"
        @if ($step) step="{{ $step }}" @endif
        {{ $attributes->class(['fi-crm-input']) }}
    />
</x-crm.field>
