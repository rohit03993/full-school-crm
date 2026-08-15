@php
    $back = \App\Support\CrmBackLink::forScopes($scopes ?? []);
@endphp

@if ($back)
    <div class="fi-crm-back">
        <a
            href="{{ $back['url'] }}"
            wire:navigate
            class="fi-crm-back__link"
            x-data="{ cameFromCrm: false }"
            x-init="
                cameFromCrm = sessionStorage.getItem('crmVisitedPage') === '1';
                sessionStorage.setItem('crmVisitedPage', '1');
            "
            x-on:click="
                if (cameFromCrm && window.history.length > 1) {
                    $event.preventDefault();
                    window.history.back();
                }
            "
        >
            <span class="fi-crm-back__icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
            </span>

            <span class="fi-crm-back__text">
                Back
                <span class="fi-crm-back__target">to {{ $back['label'] }}</span>
            </span>
        </a>
    </div>
@endif
