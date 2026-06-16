@props([
    'label',
    'model',
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'step' => null,
])

<div {{ $attributes->class(['flex flex-col gap-1.5']) }}>
    <label class="text-sm font-medium text-gray-950 dark:text-white">
        {{ $label }}@if ($required)<span class="text-danger-600 dark:text-danger-400">*</span>@endif
    </label>
    <input
        type="{{ $type }}"
        wire:model="{{ $model }}"
        placeholder="{{ $placeholder }}"
        @if ($step) step="{{ $step }}" @endif
        class="fi-input block w-full"
    />
</div>
