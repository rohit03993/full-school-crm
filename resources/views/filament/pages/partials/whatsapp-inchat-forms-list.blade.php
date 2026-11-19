@if (($part ?? 'flows') === 'flows')
<style>
    .crm-flow { display: flex; flex-direction: column; gap: 0.9rem; margin-bottom: 0.25rem; }
    .crm-flow__bar, .crm-flow__detail, .crm-flow__actions, .crm-flow__card-top, .crm-flow__stat {
        display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
    }
    .crm-flow__bar { align-items: flex-end; }
    .crm-flow__lead { margin: 0; max-width: 36rem; font-size: 0.92rem; line-height: 1.45; color: #57534e; }
    .dark .crm-flow__lead { color: #d6d3d1; }
    .crm-flow__tools { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; }
    .crm-flow__seg { display: inline-flex; padding: 0.2rem; border: 1px solid #e7e5e4; border-radius: 0.7rem; background: #fff; }
    .dark .crm-flow__seg { border-color: rgba(255,255,255,.12); background: transparent; }
    .crm-flow__seg button {
        border: 0; background: transparent; border-radius: 0.5rem; cursor: pointer; font: inherit;
        padding: 0.35rem 0.7rem; font-size: 0.82rem; font-weight: 650; color: #57534e;
    }
    .crm-flow__seg button.is-on { background: #1c1917; color: #fff; }
    .dark .crm-flow__seg button.is-on { background: #fafaf9; color: #1c1917; }
    .crm-flow__cards { display: grid; grid-template-columns: 1fr; gap: 0.85rem; }
    @media (min-width: 860px) { .crm-flow__cards { grid-template-columns: 1fr 1fr; } }
    .crm-flow__card {
        display: flex; flex-direction: column; align-items: stretch; gap: 0.85rem;
        min-height: 10.5rem; padding: 1.05rem 1.1rem 0.95rem; text-align: left;
        border: 1px solid #e7e5e4; border-radius: 0.95rem; background: #fff;
        cursor: pointer; font: inherit; color: inherit;
    }
    .dark .crm-flow__card { border-color: rgba(255,255,255,.1); background: transparent; }
    .crm-flow__card:hover { border-color: #fdba74; box-shadow: 0 10px 24px rgb(28 25 23 / 0.06); }
    .crm-flow__mark {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2.25rem; height: 2.25rem; border-radius: 0.7rem;
        background: #fff7ed; color: #c2410c; font-size: 0.78rem; font-weight: 750; letter-spacing: 0.02em;
    }
    .dark .crm-flow__mark { background: rgba(234,88,12,.15); color: #fdba74; }
    .crm-flow__name { margin: 0; font-size: 1.05rem; font-weight: 680; color: #1c1917; letter-spacing: -0.01em; }
    .dark .crm-flow__name { color: #fafaf9; }
    .crm-flow__kicker { margin: 0 0 0.35rem; font-size: 0.75rem; font-weight: 650; color: #a8a29e; }
    .crm-flow__sheet { border: 1px solid #e7e5e4; border-radius: 0.95rem; background: #fff; overflow: hidden; }
    .dark .crm-flow__sheet { border-color: rgba(255,255,255,.1); background: transparent; }
    .crm-flow__chip, .crm-flow__pill {
        display: inline-flex; align-items: center; border-radius: 999px; padding: 0.16rem 0.55rem;
        font-size: 0.75rem; font-weight: 680;
    }
    .crm-flow__chip { background: #f5f5f4; color: #44403c; }
    .dark .crm-flow__chip { background: rgba(255,255,255,.08); color: #e7e5e4; }
    .crm-flow__pill--live { background: #ecfdf3; color: #067647; }
    .crm-flow__pill--off, .crm-flow__pill--wait { background: #f5f5f4; color: #57534e; }
    .crm-flow__pill--live::before, .crm-flow__pill--off::before {
        content: ""; width: 0.4rem; height: 0.4rem; margin-right: 0.35rem; border-radius: 50%; background: currentColor;
    }
    .crm-flow__count { font-size: 0.84rem; color: #78716c; }
    .crm-flow__count strong { color: #1c1917; font-size: 1.05rem; font-weight: 720; margin-right: 0.2rem; }
    .dark .crm-flow__count strong { color: #fafaf9; }
    .crm-flow__go { font-size: 0.84rem; font-weight: 680; color: #c2410c; }
    .dark .crm-flow__go { color: #fdba74; }
    .crm-flow__btn, .crm-flow__quiet, .crm-flow__back, .crm-flow__ghost {
        border: 0; cursor: pointer; font: inherit;
    }
    .crm-flow__btn {
        border-radius: 0.65rem; background: var(--primary-600, #ea580c); color: #fff;
        padding: 0.5rem 0.9rem; font-size: 0.875rem; font-weight: 680;
    }
    .crm-flow__btn:hover { background: var(--primary-500, #f97316); }
    .crm-flow__btn:disabled { opacity: 0.6; cursor: default; }
    .crm-flow__back { background: transparent; color: #78716c; font-size: 0.85rem; font-weight: 650; padding: 0; }
    .crm-flow__back:hover { color: #1c1917; }
    .crm-flow__ghost {
        border: 1px solid #e7e5e4; border-radius: 0.65rem; background: #fff; color: #1c1917;
        padding: 0.42rem 0.75rem; font-size: 0.84rem; font-weight: 650;
    }
    .dark .crm-flow__ghost { border-color: rgba(255,255,255,.14); background: transparent; color: #fafaf9; }
    .crm-flow__ghost:hover { border-color: #fdba74; }
    .crm-flow__detail { padding: 1rem 1.15rem; gap: 1rem; }
    .crm-flow__note { margin: 0.4rem 0 0; display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; font-size: 0.88rem; color: #57534e; }
    .dark .crm-flow__note { color: #d6d3d1; }
    .crm-flow__editor { padding: 0 1.15rem 1.15rem; border-top: 1px solid #f5f5f4; }
    .dark .crm-flow__editor { border-top-color: rgba(255,255,255,.08); }
    .crm-flow__grid { display: grid; gap: 0.8rem; grid-template-columns: 1fr; margin-top: 0.9rem; }
    @media (min-width: 720px) { .crm-flow__grid { grid-template-columns: 1fr 1fr; } }
    .crm-flow__label { display: block; margin-bottom: 0.25rem; font-size: 0.78rem; font-weight: 650; color: #78716c; }
    .dark .crm-flow__label { color: #d6d3d1; }
    .crm-flow__field {
        border: 1px solid #e7e5e4; border-radius: 0.65rem; background: #fff; color: #1c1917;
        padding: 0.48rem 0.7rem; font: inherit; font-size: 0.9rem; width: 100%;
    }
    .dark .crm-flow__field { border-color: rgba(255,255,255,.12); background: transparent; color: #fafaf9; }
    .crm-flow__check { display: flex; align-items: center; gap: 0.45rem; margin-top: 0.8rem; font-size: 0.9rem; color: #44403c; }
    .crm-flow__locked { margin: 0.7rem 0 0; font-size: 0.84rem; color: #78716c; }
    .crm-flow__empty { margin: 0; padding: 1.4rem 1.15rem; font-size: 0.9rem; color: #78716c; }
    .crm-flow__person {
        display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 0.75rem; align-items: start;
        padding: 0.85rem 1.15rem; border-top: 1px solid #f5f5f4;
    }
    .dark .crm-flow__person { border-top-color: rgba(255,255,255,.08); }
    .crm-flow__avatar {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2rem; height: 2rem; border-radius: 50%; background: #f5f5f4; color: #44403c;
        font-size: 0.78rem; font-weight: 720;
    }
    .crm-flow__when { font-size: 0.78rem; color: #a8a29e; white-space: nowrap; padding-top: 0.15rem; }
    .crm-flow__answers { margin: 0.28rem 0 0; font-size: 0.84rem; line-height: 1.45; color: #44403c; }
    .dark .crm-flow__answers { color: #e7e5e4; }
    .crm-flow__link { color: #1c1917; font-weight: 680; text-decoration: none; }
    .crm-flow__link:hover { color: #c2410c; }
    .dark .crm-flow__link { color: #fafaf9; }
    .crm-flow__send {
        display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center;
        padding: 0.85rem 1.15rem; border-top: 1px solid #f5f5f4; background: #fafaf9;
    }
    .dark .crm-flow__send { border-top-color: rgba(255,255,255,.08); background: transparent; }
    .crm-flow__pick {
        border: 1px solid #e7e5e4; border-radius: 0.65rem; background: #fff; color: #1c1917;
        padding: 0.48rem 0.7rem; font: inherit; font-size: 0.9rem; min-width: 14rem; flex: 1 1 14rem;
    }
    .dark .crm-flow__pick { border-color: rgba(255,255,255,.12); background: transparent; color: #fafaf9; }
    .crm-flow__search { display: block; width: min(100%, 16rem); }
    @media (max-width: 640px) {
        .crm-flow__person { grid-template-columns: auto minmax(0, 1fr); }
        .crm-flow__when { grid-column: 2; padding-top: 0; }
    }
</style>

@php
    $cards = $cards ?? [];
    $filter = $listFilter ?? 'all';
    $open = collect($cards)->firstWhere('key', $openKey ?? '');
    $visible = array_values(array_filter($cards, function (array $card) use ($filter): bool {
        if ($filter === 'on') {
            return (bool) ($card['on'] ?? false);
        }
        if ($filter === 'off') {
            return ! ($card['on'] ?? false);
        }

        return true;
    }));
    $pill = function (string $state): string {
        return $state === 'Live' ? 'crm-flow__pill--live' : ($state === 'Off' ? 'crm-flow__pill--off' : 'crm-flow__pill--wait');
    };
    $mark = function (string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = mb_strtoupper(mb_substr((string) ($parts[0] ?? 'F'), 0, 1).mb_substr((string) ($parts[1] ?? ''), 0, 1));

        return $letters !== '' ? $letters : 'F';
    };
@endphp

<div class="crm-flow">
    @if ($open)
        <button type="button" wire:click="backToFlows" class="crm-flow__back">← All flows</button>
        <section class="crm-flow__sheet">
            <div class="crm-flow__detail">
                <div>
                    <p class="crm-flow__kicker">Keyword {{ $open['word'] }}</p>
                    <h2 class="crm-flow__name">{{ $open['name'] }}</h2>
                    <p class="crm-flow__note">
                        <span class="crm-flow__pill {{ $pill($open['state']) }}">{{ $open['state'] }}</span>
                        <span>{{ $open['count'] }} {{ $open['count'] === 1 ? 'reply' : 'replies' }}</span>
                    </p>
                </div>
                <div class="crm-flow__actions">
                    @if ($open['kind'] === 'visit')
                        <button type="button" wire:click="editVisit" class="crm-flow__ghost">Edit</button>
                        @if ($open['on'])
                            <button type="button" wire:click="setVisitEnabled(false)" class="crm-flow__ghost">Turn off</button>
                        @else
                            <button type="button" wire:click="setVisitEnabled(true)" class="crm-flow__ghost">Turn on</button>
                        @endif
                    @else
                        <button type="button" wire:click="editFlow({{ (int) $open['id'] }})" class="crm-flow__ghost">Edit</button>
                        @if ($open['on'])
                            <button type="button" wire:click="setFlowEnabled({{ (int) $open['id'] }}, false)" class="crm-flow__ghost">Turn off</button>
                        @else
                            <button type="button" wire:click="setFlowEnabled({{ (int) $open['id'] }}, true)" class="crm-flow__ghost">Turn on</button>
                        @endif
                    @endif
                </div>
            </div>
            @if (($editingVisit ?? false) && $open['kind'] === 'visit')
                <div id="flow-editor" class="crm-flow__editor">
                    <p class="crm-flow__note">Change the words parents see. Course and visit date stay, because a booking needs both.</p>
                    <div class="crm-flow__grid">
                        <label>
                            <span class="crm-flow__label">Course question</span>
                            <input type="text" wire:model="visitCourseLabel" maxlength="30" class="crm-flow__field" />
                        </label>
                        <label>
                            <span class="crm-flow__label">Date question</span>
                            <input type="text" wire:model="visitDateLabel" maxlength="30" class="crm-flow__field" />
                        </label>
                        <label>
                            <span class="crm-flow__label">Note question</span>
                            <input type="text" wire:model="visitNoteLabel" maxlength="30" class="crm-flow__field" />
                        </label>
                        <label>
                            <span class="crm-flow__label">Button text</span>
                            <input type="text" wire:model="visitButton" maxlength="20" class="crm-flow__field" />
                        </label>
                    </div>
                    <label class="crm-flow__check">
                        <input type="checkbox" wire:model="visitNoteRequired" />
                        Note is required
                    </label>
                    <p class="crm-flow__locked">Word stays visit. These three questions stay.</p>
                    <div class="crm-flow__actions" style="margin-top: 1rem;">
                        <button type="button" wire:click="saveVisit" wire:loading.attr="disabled" wire:target="saveVisit" class="crm-flow__btn">
                            <span wire:loading.remove wire:target="saveVisit">Save and update WhatsApp</span>
                            <span wire:loading wire:target="saveVisit">Updating…</span>
                        </button>
                        <button type="button" wire:click="closeFlowEditor" class="crm-flow__ghost">Close</button>
                    </div>
                </div>
            @endif
        </section>
    @else
        <div class="crm-flow__bar">
            <p class="crm-flow__lead">Each card is one flow. Open it to see who replied, edit the questions, or turn the word off.</p>
            <div class="crm-flow__tools">
                <div class="crm-flow__seg" role="group" aria-label="Which flows">
                    <button type="button" wire:click="$set('listFilter', 'all')" class="{{ $filter === 'all' ? 'is-on' : '' }}">All</button>
                    <button type="button" wire:click="$set('listFilter', 'on')" class="{{ $filter === 'on' ? 'is-on' : '' }}">On</button>
                    <button type="button" wire:click="$set('listFilter', 'off')" class="{{ $filter === 'off' ? 'is-on' : '' }}">Off</button>
                </div>
                <button type="button" wire:click="startNewFlow" class="crm-flow__btn">New flow</button>
            </div>
        </div>
        @if ($visible === [])
            <section class="crm-flow__sheet">
                <p class="crm-flow__empty">No flows in this view.</p>
            </section>
        @else
            <div class="crm-flow__cards">
                @foreach ($visible as $card)
                    <button type="button" wire:click="openFlow('{{ $card['key'] }}')" class="crm-flow__card">
                        <span class="crm-flow__card-top">
                            <span class="crm-flow__mark">{{ $mark($card['name']) }}</span>
                            <span class="crm-flow__pill {{ $pill($card['state']) }}">{{ $card['state'] }}</span>
                        </span>
                        <span>
                            <span class="crm-flow__name">{{ $card['name'] }}</span>
                            <span class="crm-flow__note">Keyword <span class="crm-flow__chip">{{ $card['word'] }}</span></span>
                        </span>
                        <span class="crm-flow__stat">
                            <span class="crm-flow__count"><strong>{{ $card['count'] }}</strong> {{ $card['count'] === 1 ? 'reply' : 'replies' }}</span>
                            <span class="crm-flow__go">Open</span>
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
        <div id="flow-editor"></div>
    @endif
</div>
@endif
