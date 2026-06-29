@extends('layouts.portal')

@section('title', 'My Portal')
@section('heading', $student->name)
@section('subheading', $student->mobile.' · '.$student->status->label())

@section('avatar')
    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-sm font-bold text-navy-950 shadow-md ring-2 ring-white" aria-hidden="true">
        {{ $student->initials() }}
    </div>
@endsection

@section('content')
    @php
        $initialTab = 'home';
        if ($errors->has('current_password') || $errors->has('password')) {
            $initialTab = 'more';
        } elseif ($errors->has('admission') || $errors->has('documents')) {
            $initialTab = 'admission';
        }

        $tabs = collect([
            ['id' => 'home', 'label' => 'Overview'],
            ['id' => 'fees', 'label' => 'Fees'],
            ['id' => 'marks', 'label' => 'Marks', 'hidden' => empty($examMarksSections)],
            ['id' => 'admission', 'label' => 'Admission', 'hidden' => ! $admission],
            ['id' => 'more', 'label' => 'More'],
        ])->filter(fn (array $tab): bool => empty($tab['hidden']))->values();
    @endphp

    <div
        x-data="{
            tab: @js($initialTab),
            tabs: @js($tabs->pluck('id')->all()),
            init() {
                const hash = window.location.hash.replace('#', '');
                if (this.tabs.includes(hash)) {
                    this.tab = hash;
                }
                window.addEventListener('hashchange', () => {
                    const next = window.location.hash.replace('#', '');
                    if (this.tabs.includes(next)) {
                        this.tab = next;
                        window.portalNavRefresh?.();
                    }
                });
            },
            setTab(next) {
                if (! this.tabs.includes(next)) return;
                this.tab = next;
                history.replaceState(null, '', '#' + next);
                window.portalNavRefresh?.();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }"
        x-init="init()"
        class="space-y-4"
    >
        <div class="-mx-1 overflow-x-auto px-1 pb-1 scrollbar-none lg:hidden">
            <div class="flex min-w-max gap-2" role="tablist" aria-label="Portal sections">
                @foreach ($tabs as $tab)
                    <button
                        type="button"
                        role="tab"
                        @click="setTab(@js($tab['id']))"
                        :aria-selected="tab === @js($tab['id'])"
                        :class="tab === @js($tab['id']) ? 'portal-tab-btn portal-tab-btn--active' : 'portal-tab-btn portal-tab-btn--idle'"
                    >
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="space-y-4">
            <div x-show="tab === 'home'" x-cloak role="tabpanel" class="space-y-4">
                @include('portal.partials.dashboard.home')
            </div>

            <div x-show="tab === 'fees'" x-cloak role="tabpanel" class="space-y-4">
                @include('portal.partials.dashboard.fees')
            </div>

            @if (! empty($examMarksSections))
                <div x-show="tab === 'marks'" x-cloak role="tabpanel">
                    @include('portal.partials.dashboard.marks')
                </div>
            @endif

            @if ($admission)
                <div x-show="tab === 'admission'" x-cloak role="tabpanel" class="space-y-4">
                    @include('portal.partials.dashboard.admission')
                </div>
            @endif

            <div x-show="tab === 'more'" x-cloak role="tabpanel" class="space-y-4">
                @include('portal.partials.dashboard.more')
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
@endsection
