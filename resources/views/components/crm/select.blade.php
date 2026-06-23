@props([
    'disabled' => false,
])

<select
    @disabled($disabled)
    {{ $attributes->class(['fi-crm-select']) }}
>
    {{ $slot }}
</select>
