@if (($part ?? 'flows') === 'flows')
<style>
    .crm-flow { display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 0.25rem; }
    .crm-flow__bar, .crm-flow__detail, .crm-flow__actions {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem 1rem;
    }
    .crm-flow__lead { margin: 0; font-size: 0.95rem; line-height: 1.45; color: #57534e; }
    .dark .crm-flow__lead { color: #d6d3d1; }
    .crm-flow__sheet { border: 1px solid #e7e5e4; border-radius: 0.85rem; background: #fff; overflow: hidden; }
    .dark .crm-flow__sheet { border-color: rgba(255,255,255,.1); background: transparent; }
    .crm-flow__item {
        display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(6rem, 0.7fr) 7.5rem 5.5rem auto;
        gap: 0.75rem; align-items: center; width: 100%; text-align: left;
        padding: 0.95rem 1.15rem; border: 0; border-bottom: 1px solid #f5f5f4;
        background: transparent; cursor: pointer; font: inherit; color: inherit;
    }
    .crm-flow__item:last-child { border-bottom: 0; }
    .crm-flow__item:hover { background: #fff7ed; }
    .dark .crm-flow__item { border-bottom-color: rgba(255,255,255,.08); }
    .dark .crm-flow__item:hover { background: rgba(234,88,12,.08); }
    .crm-flow__name { margin: 0; font-size: 1rem; font-weight: 650; color: #1c1917; }
    .dark .crm-flow__name { color: #fafaf9; }
    .crm-flow__word, .crm-flow__count, .crm-flow__go { font-size: 0.85rem; color: #78716c; }
    .crm-flow__go { font-weight: 650; color: #c2410c; text-align: right; }
    .dark .crm-flow__go { color: #fdba74; }
    .crm-flow__chip, .crm-flow__pill {
        display: inline-flex; align-items: center; border-radius: 999px; padding: 0.12rem 0.55rem;
        font-size: 0.75rem; font-weight: 650;
    }
    .crm-flow__chip { background: #f5f5f4; color: #44403c; }
    .dark .crm-flow__chip { background: rgba(255,255,255,.08); color: #e7e5e4; }
    .crm-flow__pill { background: #fff7ed; color: #c2410c; }
    .dark .crm-flow__pill { background: rgba(234,88,12,.15); color: #fdba74; }
    .crm-flow__pill--wait { background: #f5f5f4; color: #57534e; }
    .crm-flow__btn, .crm-flow__quiet, .crm-flow__back {
        border: 0; background: transparent; cursor: pointer; font: inherit;
    }
    .crm-flow__btn {
        border-radius: 0.65rem; background: var(--primary-600, #ea580c); color: #fff;
        padding: 0.5rem 0.9rem; font-size: 0.875rem; font-weight: 650;
    }
    .crm-flow__btn:hover { background: var(--primary-500, #f97316); }
    .crm-flow__btn:disabled { opacity: 0.6; cursor: default; }
    .crm-flow__quiet, .crm-flow__back { color: #c2410c; font-size: 0.875rem; font-weight: 650; }
    .dark .crm-flow__quiet, .dark .crm-flow__back { color: #fdba74; }
    .crm-flow__detail { padding: 1rem 1.15rem; }
    .crm-flow__note { margin: 0.35rem 0 0; font-size: 0.9rem; color: #57534e; }
    .dark .crm-flow__note { color: #d6d3d1; }
    .crm-flow__editor { padding: 0 1.15rem 1.15rem; border-top: 1px solid #f5f5f4; }
    .dark .crm-flow__editor { border-top-color: rgba(255,255,255,.08); }
    .crm-flow__grid { display: grid; gap: 0.8rem; grid-template-columns: 1fr; margin-top: 0.9rem; }
    @media (min-width: 720px) { .crm-flow__grid { grid-template-columns: 1fr 1fr; } }
    .crm-flow__label { display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 650; color: #44403c; }
    .dark .crm-flow__label { color: #e7e5e4; }
    .crm-flow__field {
        border: 1px solid #e7e5e4; border-radius: 0.65rem; background: #fff; color: #1c1917;
        padding: 0.45rem 0.65rem; font: inherit; font-size: 0.9rem; width: 100%;
    }
    .dark .crm-flow__field { border-color: rgba(255,255,255,.12); background: transparent; color: #fafaf9; }
    .crm-flow__check { display: flex; align-items: center; gap: 0.45rem; margin-top: 0.8rem; font-size: 0.9rem; color: #44403c; }
    .crm-flow__locked { margin: 0.7rem 0 0; font-size: 0.85rem; color: #78716c; }
    .crm-flow__empty { margin: 0; padding: 1.1rem 1.15rem; font-size: 0.9rem; color: #78716c; }
    .crm-flow__person { padding: 0.85rem 1.15rem; border-top: 1px solid #f5f5f4; }
    .dark .crm-flow__person { border-top-color: rgba(255,255,255,.08); }
    .crm-flow__answers { margin: 0.25rem 0 0; font-size: 0.85rem; line-height: 1.45; color: #44403c; }
    .dark .crm-flow__answers { color: #e7e5e4; }
    .crm-flow__link { color: #c2410c; font-weight: 650; text-decoration: none; }
    .dark .crm-flow__link { color: #fdba74; }
    .crm-flow__send { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; padding: 0.85rem 1.15rem 1rem; border-top: 1px solid #f5f5f4; }
    .dark .crm-flow__send { border-top-color: rgba(255,255,255,.08); }
    .crm-flow__pick {
        border: 1px solid #e7e5e4; border-radius: 0.65rem; background: #fff; color: #1c1917;
        padding: 0.45rem 0.65rem; font: inherit; font-size: 0.9rem; min-width: 14rem;
    }
    .dark .crm-flow__pick { border-color: rgba(255,255,255,.12); background: transparent; color: #fafaf9; }
    .crm-flow__search { display: block; max-width: 18rem; }
    @media (max-width: 720px) {
        .crm-flow__item { grid-template-columns: minmax(0, 1fr) auto; }
        .crm-flow__word, .crm-flow__count { display: none; }
    }
</style>

@php
    $cards = $cards ?? [];
    $open = collect($cards)->firstWhere('key', $openKey ?? '');
@endphp

<div class="crm-flow">
    @if ($open)
        <button type="button" wire:click="backToFlows" class="crm-flow__back">All flows</button>
        <section class="crm-flow__sheet">
            <div class="crm-flow__detail">
                <div>
                    <h2 class="crm-flow__name">{{ $open['name'] }}</h2>
                    <p class="crm-flow__note">
                        Word <span class="crm-flow__chip">{{ $open['word'] }}</span>
                        <span class="crm-flow__pill {{ $open['state'] === 'Live' ? '' : 'crm-flow__pill--wait' }}">{{ $open['state'] }}</span>
                    </p>
                </div>
                <div class="crm-flow__actions">
                    @if ($open['kind'] === 'visit')
                        <button type="button" wire:click="editVisit" class="crm-flow__quiet">Edit</button>
                        @if ($open['on'])
                            <button type="button" wire:click="setVisitEnabled(false)" class="crm-flow__quiet">Turn off</button>
                        @else
                            <button type="button" wire:click="setVisitEnabled(true)" class="crm-flow__quiet">Turn on</button>
                        @endif
                    @else
                        <button type="button" wire:click="editFlow({{ (int) $open['id'] }})" class="crm-flow__quiet">Edit</button>
                        @if ($open['on'])
                            <button type="button" wire:click="setFlowEnabled({{ (int) $open['id'] }}, false)" class="crm-flow__quiet">Turn off</button>
                        @else
                            <button type="button" wire:click="setFlowEnabled({{ (int) $open['id'] }}, true)" class="crm-flow__quiet">Turn on</button>
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
                        <button type="button" wire:click="closeFlowEditor" class="crm-flow__quiet">Close</button>
                    </div>
                </div>
            @endif
        </section>
    @else
        <div class="crm-flow__bar">
            <p class="crm-flow__lead">Open a flow to see who replied. Turn it on or off from that screen.</p>
            <button type="button" wire:click="startNewFlow" class="crm-flow__btn">New flow</button>
        </div>
        <section class="crm-flow__sheet">
            @if ($cards === [])
                <p class="crm-flow__empty">No flows yet.</p>
            @else
                @foreach ($cards as $card)
                    <button type="button" wire:click="openFlow('{{ $card['key'] }}')" class="crm-flow__item">
                        <span class="crm-flow__name">{{ $card['name'] }}</span>
                        <span class="crm-flow__word">{{ $card['word'] }}</span>
                        <span><span class="crm-flow__pill {{ $card['state'] === 'Live' ? '' : 'crm-flow__pill--wait' }}">{{ $card['state'] }}</span></span>
                        <span class="crm-flow__count">{{ $card['count'] }} replied</span>
                        <span class="crm-flow__go">Open</span>
                    </button>
                @endforeach
            @endif
        </section>
        <div id="flow-editor"></div>
    @endif
</div>
@endif
