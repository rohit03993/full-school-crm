@props([
    'label',
    'for' => null,
    'required' => false,
])

<div {{ $attributes->class(['fi-fo-field w-full']) }}>
    <div class="fi-fo-field-label-col">
        <label @if ($for) for="{{ $for }}" @endif class="fi-fo-field-wrp-label">
            <span class="fi-fo-field-label-content">
                {{ $label }}@if ($required)<span class="text-danger-600 dark:text-danger-400">*</span>@endif
            </span>
        </label>
    </div>
    <div class="fi-fo-field-content-col">
        {{ $slot }}
    </div>
</div>
