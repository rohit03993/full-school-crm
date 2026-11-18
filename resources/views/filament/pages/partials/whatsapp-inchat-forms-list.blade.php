<style>
    .crm-flow { display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 1.5rem; }
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
    .crm-flow__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; }
    .crm-flow__btn, .crm-flow__quiet, .crm-flow__edit {
        border: 0; background: transparent; cursor: pointer; font: inherit;
    }
    .crm-flow__btn {
        border-radius: 0.7rem; background: var(--primary-600, #ea580c); color: #fff;
        padding: 0.5rem 0.9rem; font-size: 0.875rem; font-weight: 650;
    }
    .crm-flow__btn:hover { background: var(--primary-500, #f97316); }
    .crm-flow__btn:disabled { opacity: 0.6; cursor: default; }
    .crm-flow__quiet, .crm-flow__edit { color: #c2410c; font-size: 0.85rem; font-weight: 650; }
    .dark .crm-flow__quiet, .dark .crm-flow__edit { color: #fdba74; }
    .crm-flow__head { padding: 0.9rem 1.25rem; border-bottom: 1px solid #e7e5e4; }
    .dark .crm-flow__head { border-bottom-color: rgba(255,255,255,.1); }
    .crm-flow__row { padding: 0.85rem 1.25rem; }
    .crm-flow__row + .crm-flow__row { border-top: 1px solid #f5f5f4; }
    .dark .crm-flow__row + .crm-flow__row { border-top-color: rgba(255,255,255,.08); }
    .crm-flow__empty { margin: 0; padding: 1rem 1.25rem 1.15rem; font-size: 0.9rem; color: #78716c; }
    .crm-flow__note { margin: 0; font-size: 0.9rem; color: #57534e; }
    .dark .crm-flow__note { color: #d6d3d1; }
</style>

<div class="crm-flow">
    <p class="crm-flow__lead">Someone types a word on WhatsApp and this form opens. Campus visit is already set up. Your own flows sit under it.</p>

    <article class="crm-flow__sheet crm-flow__visit">
        <div>
            <p class="crm-flow__kicker">Built in</p>
            <h2 class="crm-flow__title">Campus visit</h2>
            <p class="crm-flow__meta">
                Word <span class="crm-flow__chip">visit</span>
                @if ($visitPublished ?? false)
                    <span class="crm-flow__pill">Live</span>
                @else
                    <span class="crm-flow__pill crm-flow__pill--wait">Not on WhatsApp yet</span>
                @endif
            </p>
        </div>
        <div class="crm-flow__actions">
            @unless ($visitPublished ?? false)
                <button type="button" wire:click="publishVisit" wire:loading.attr="disabled" wire:target="publishVisit" class="crm-flow__btn">
                    <span wire:loading.remove wire:target="publishVisit">Publish</span>
                    <span wire:loading wire:target="publishVisit">Publishing…</span>
                </button>
            @else
                <button type="button" wire:click="publishVisit" wire:loading.attr="disabled" wire:target="publishVisit" class="crm-flow__quiet" title="Sends this same visit form to WhatsApp again. Use it only if the form stopped opening in chats.">
                    <span wire:loading.remove wire:target="publishVisit">Update on WhatsApp</span>
                    <span wire:loading wire:target="publishVisit">Updating…</span>
                </button>
            @endunless
        </div>
    </article>

    <section class="crm-flow__sheet">
        <div class="crm-flow__head">
            <h2 class="crm-flow__title">Your flows</h2>
            <button type="button" wire:click="startNewFlow" class="crm-flow__btn">New flow</button>
        </div>
        @if (($flows ?? []) === [])
            <p class="crm-flow__empty">None yet. New flow asks for a name, one word, and one question.</p>
        @else
            @foreach ($flows as $flow)
                <div class="crm-flow__row">
                    <div>
                        <p class="crm-flow__title">{{ $flow['name'] }}</p>
                        <p class="crm-flow__meta">
                            Word <span class="crm-flow__chip">{{ $flow['trigger_word'] }}</span>
                            <span class="crm-flow__pill {{ $flow['state'] === 'Published' ? '' : 'crm-flow__pill--wait' }}">{{ $flow['state'] }}</span>
                        </p>
                    </div>
                    <button type="button" wire:click="editFlow({{ (int) $flow['id'] }})" class="crm-flow__edit">Edit</button>
                </div>
            @endforeach
        @endif
    </section>

    @if ($showEditor ?? false)
        <p class="crm-flow__note">Type the name, the word, and the questions. Save and publish puts it on WhatsApp. A draft stays here until you do that.</p>
    @endif
</div>
