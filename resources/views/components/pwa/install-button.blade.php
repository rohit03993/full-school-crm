<button
    type="button"
    {{ $attributes->merge(['class' => 'crm-pwa-install-trigger hidden']) }}
    data-crm-pwa-install
    hidden
>
    {{ $slot->isEmpty() ? 'Install app' : $slot }}
</button>
