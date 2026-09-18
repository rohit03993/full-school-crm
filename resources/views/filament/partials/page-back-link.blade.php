@php
    $back = \App\Support\CrmBackLink::forScopes($scopes ?? []);

    $plain = static function (mixed $value): ?string {
        if ($value instanceof \Illuminate\Contracts\Support\Htmlable) {
            $value = $value->toHtml();
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text !== '' ? $text : null;
    };

    $pageTitle = null;
    $pageHint = null;

    try {
        $livewire = \Livewire\Livewire::current();
    } catch (\Throwable) {
        $livewire = null;
    }

    $skipCompactTitle = $livewire instanceof \App\Filament\Pages\StudentProfilePage;

    if ($livewire && ! $skipCompactTitle) {
        try {
            if (method_exists($livewire, 'getHeading')) {
                $pageTitle = $plain($livewire->getHeading());
            }

            if (! filled($pageTitle) && method_exists($livewire, 'getTitle')) {
                $pageTitle = $plain($livewire->getTitle());
            }

            if (method_exists($livewire, 'getSubheading')) {
                $pageHint = $plain($livewire->getSubheading());
            }
        } catch (\Throwable) {
            $pageTitle = null;
            $pageHint = null;
        }
    }
@endphp

@if ($back)
    <div @class(['fi-crm-back', 'fi-crm-back--with-title' => filled($pageTitle)])>
        <div class="fi-crm-back__row">
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

            @if (filled($pageTitle))
                <h1 class="fi-crm-back__title">{{ $pageTitle }}</h1>
            @endif
        </div>

        @if (filled($pageHint))
            <p class="fi-crm-back__hint">{{ $pageHint }}</p>
        @endif
    </div>
@endif
