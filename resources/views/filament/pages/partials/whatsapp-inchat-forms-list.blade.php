@if (($part ?? 'flows') === 'flows')
<style>
    .crm-flow { display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 0.25rem; }
    .crm-flow__lead { margin: 0; max-width: 40rem; font-size: 0.95rem; line-height: 1.5; color: #57534e; }
    .dark .crm-flow__lead { color: #d6d3d1; }
    .crm-flow__sheet { border: 1px solid #e7e5e4; border-radius: 1rem; background: #fff; }
    .dark .crm-flow__sheet { border-color: rgba(255,255,255,.1); background: transparent; }
    .crm-flow__visit, .crm-flow__head, .crm-flow__row {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem 1rem;
    }
    .crm-flow__visit { padding: 1.1rem 1.25rem; }
    .crm-flow__kicker { margin: 0; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #a8a29e; }
    .crm-flow__title { margin: 0.15rem 0 0; font-size: 1.05rem; font-weight: 650; color: #1c1917; }
    .dark .crm-flow__title { color: #fafaf9; }
    .crm-flow__meta { margin: 0.35rem 0 0; display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: #78716c; }
    .crm-flow__chip, .crm-flow__pill {
        display: inline-flex; align-items: center; border-radius: 999px; padding: 0.1rem 0.55rem; font-size: 0.75rem; font-weight: 650;
    }
    .crm-flow__chip { background: #f5f5f4; color: #44403c; }
    .dark .crm-flow__chip { background: rgba(255,255,255,.08); color: #e7e5e4; }
    .crm-flow__pill { background: #fff7ed; color: #c2410c; }
    .dark .crm-flow__pill { background: rgba(234,88,12,.15); color: #fdba74; }
    .crm-flow__pill--wait { background: #f5f5f4; color: #57534e; }
    .crm-flow__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.85rem; }
    .crm-flow__btn, .crm-flow__quiet, .crm-flow__edit {
        border: 0; background: transparent; cursor: pointer; font: inherit;
    }
    .crm-flow__btn {
        border-radius: 0.7rem; background: var(--primary-600, #ea580c); color: #fff;
        padding: 0.5rem 0.9rem; font-size: 0.875rem; font-weight: 650;
    }
    .crm-flow__btn:hover { background: var(--primary-500, #f97316); }
    .crm-flow__btn:disabled { opacity: 0.6; cursor: default; }
    .crm-flow__quiet, .crm-flow__edit { color: #c2410c; font-size: 0.85rem; font-weight: 650; text-decoration: none; }
    .dark .crm-flow__quiet, .dark .crm-flow__edit { color: #fdba74; }
    .crm-flow__head { padding: 0.9rem 1.25rem; border-bottom: 1px solid #e7e5e4; }
    .dark .crm-flow__head { border-bottom-color: rgba(255,255,255,.1); }
    .crm-flow__row { padding: 0.85rem 1.25rem; }
    .crm-flow__open { box-shadow: inset 3px 0 0 var(--primary-600, #ea580c); background: #fff7ed; }
    .dark .crm-flow__open { background: rgba(234,88,12,.08); }
    .crm-flow__row + .crm-flow__row { border-top: 1px solid #f5f5f4; }
    .dark .crm-flow__row + .crm-flow__row { border-top-color: rgba(255,255,255,.08); }
    .crm-flow__empty { margin: 0; padding: 1rem 1.25rem 1.15rem; font-size: 0.9rem; color: #78716c; }
    .crm-flow__note { margin: 0; font-size: 0.9rem; color: #57534e; }
    .dark .crm-flow__note { color: #d6d3d1; }
    .crm-flow__pick, .crm-flow__field {
        border: 1px solid #e7e5e4; border-radius: 0.7rem; background: #fff; color: #1c1917;
        padding: 0.45rem 0.65rem; font: inherit; font-size: 0.9rem; width: 100%;
    }
    .dark .crm-flow__pick, .dark .crm-flow__field { border-color: rgba(255,255,255,.12); background: transparent; color: #fafaf9; }
    .crm-flow__pick { width: auto; }
    .crm-flow__answers { margin: 0.3rem 0 0; max-width: 36rem; font-size: 0.85rem; line-height: 1.45; color: #44403c; }
    .dark .crm-flow__answers { color: #e7e5e4; }
    .crm-flow__editor { padding: 1.1rem 1.25rem 1.25rem; }
    .crm-flow__grid { display: grid; gap: 0.8rem; grid-template-columns: 1fr; }
    @media (min-width: 720px) { .crm-flow__grid { grid-template-columns: 1fr 1fr; } }
    .crm-flow__label { display: block; margin-bottom: 0.25rem; font-size: 0.8rem; font-weight: 650; color: #44403c; }
    .dark .crm-flow__label { color: #e7e5e4; }
    .crm-flow__check { display: flex; align-items: center; gap: 0.45rem; font-size: 0.9rem; color: #44403c; }
    .crm-flow__locked { margin: 0.7rem 0 0; font-size: 0.85rem; color: #78716c; }
    .crm-flow__replies { border-top: 1px solid #f5f5f4; padding: 0.35rem 0 0.2rem; }
    .dark .crm-flow__replies { border-top-color: rgba(255,255,255,.08); }
    .crm-flow__send { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; padding: 0.75rem 1.25rem 1rem; }
    .crm-flow__search { display: block; max-width: 18rem; }
</style>

<div class="crm-flow">
    <p class="crm-flow__lead">Each form keeps its own replies. Edit changes the questions. Turn off stops the word. People who already replied stay on that form.</p>
    <label class="crm-flow__search">
        <span class="crm-flow__label">Find a reply</span>
        <input type="search" wire:model.live.debounce.400ms="replySearch" placeholder="Name or number" class="crm-flow__field" />
    </label>

    @php
        $visitLive = ($visitPublished ?? false) && ($visitOn ?? true);
        $visitState = ! ($visitOn ?? true) ? 'Off' : (($visitPublished ?? false) ? 'Live' : 'Not on WhatsApp');
    @endphp
    <section class="crm-flow__sheet">
    <article class="crm-flow__visit {{ ($editingVisit ?? false) ? 'crm-flow__open' : '' }}">
        <div>
            <p class="crm-flow__kicker">Built in</p>
            <h2 class="crm-flow__title">Campus visit</h2>
            <p class="crm-flow__meta">
                Word <span class="crm-flow__chip">visit</span>
                <span class="crm-flow__pill {{ $visitLive ? '' : 'crm-flow__pill--wait' }}">{{ $visitState }}</span>
                @if ($editingVisit ?? false)
                    <span class="crm-flow__pill">Editing this one</span>
                @endif
            </p>
        </div>
        <div class="crm-flow__actions">
            <button type="button" wire:click="editVisit" class="crm-flow__edit">Edit</button>
            @if ($visitOn ?? true)
                <button type="button" wire:click="setVisitEnabled(false)" class="crm-flow__quiet">Turn off</button>
            @else
                <button type="button" wire:click="setVisitEnabled(true)" class="crm-flow__quiet">Turn on</button>
            @endif
        </div>
    </article>
    @include('filament.pages.partials.whatsapp-flow-replies', [
        'flowKey' => 'campus',
        'rows' => $replyPacks['campus']['rows'] ?? [],
        'count' => $replyPacks['campus']['count'] ?? 0,
        'templates' => $templates ?? [],
    ])
    </section>

    <section class="crm-flow__sheet">
        <div class="crm-flow__head">
            <h2 class="crm-flow__title">Your flows</h2>
            <button type="button" wire:click="startNewFlow" class="crm-flow__btn">New flow</button>
        </div>
        @if (($flows ?? []) === [])
            <p class="crm-flow__empty">None yet. New flow asks for a name, one word, and one question.</p>
        @else
            @foreach ($flows as $flow)
                <div class="crm-flow__row {{ (int) ($editingId ?? 0) === (int) $flow['id'] ? 'crm-flow__open' : '' }}">
                    <div>
                        <p class="crm-flow__title">{{ $flow['name'] }}</p>
                        <p class="crm-flow__meta">
                            Word <span class="crm-flow__chip">{{ $flow['trigger_word'] }}</span>
                            <span class="crm-flow__pill {{ $flow['state'] === 'Live' ? '' : 'crm-flow__pill--wait' }}">{{ $flow['state'] }}</span>
                            @if ((int) ($editingId ?? 0) === (int) $flow['id'])
                                <span class="crm-flow__pill">Editing this one</span>
                            @endif
                        </p>
                    </div>
                    <div class="crm-flow__actions">
                        <button type="button" wire:click="editFlow({{ (int) $flow['id'] }})" class="crm-flow__edit">Edit</button>
                        @if ($flow['enabled'] ?? true)
                            <button type="button" wire:click="setFlowEnabled({{ (int) $flow['id'] }}, false)" class="crm-flow__quiet">Turn off</button>
                        @else
                            <button type="button" wire:click="setFlowEnabled({{ (int) $flow['id'] }}, true)" class="crm-flow__quiet">Turn on</button>
                        @endif
                    </div>
                </div>
                @include('filament.pages.partials.whatsapp-flow-replies', [
                    'flowKey' => 'form_'.$flow['id'],
                    'rows' => $replyPacks['form_'.$flow['id']]['rows'] ?? [],
                    'count' => $replyPacks['form_'.$flow['id']]['count'] ?? 0,
                    'templates' => $templates ?? [],
                ])
            @endforeach
        @endif
    </section>

    <div id="flow-editor">
        @if ($editingVisit ?? false)
            <section class="crm-flow__sheet">
                <div class="crm-flow__editor">
                    <p class="crm-flow__kicker">You are editing</p>
                    <h2 class="crm-flow__title">Campus visit</h2>
                    <p class="crm-flow__note">Change the words parents see. Course and visit date stay, because a booking needs both. Old replies stay in the list.</p>
                    <div class="crm-flow__grid" style="margin-top: 0.9rem;">
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
                    <label class="crm-flow__check" style="margin-top: 0.8rem;">
                        <input type="checkbox" wire:model="visitNoteRequired" />
                        Note is required
                    </label>
                    <p class="crm-flow__locked">Word stays <strong>visit</strong>. You cannot add or remove these three questions.</p>
                    <div class="crm-flow__actions" style="margin-top: 1rem;">
                        <button type="button" wire:click="saveVisit" wire:loading.attr="disabled" wire:target="saveVisit" class="crm-flow__btn">
                            <span wire:loading.remove wire:target="saveVisit">Save and update WhatsApp</span>
                            <span wire:loading wire:target="saveVisit">Updating…</span>
                        </button>
                        <button type="button" wire:click="closeFlowEditor" class="crm-flow__quiet">Close</button>
                    </div>
                </div>
            </section>
        @endif
    </div>
</div>
@endif
