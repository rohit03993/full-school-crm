<style>
    .crm-market { display: flex; flex-direction: column; gap: 0.9rem; }
    .crm-market__bar, .crm-market__send { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 0.75rem; }
    .crm-market__lead { margin: 0; max-width: 40rem; color: #57534e; line-height: 1.45; }
    .crm-market__sheet { border: 1px solid #e7e5e4; border-radius: 0.9rem; background: #fff; overflow: hidden; }
    .crm-market__row { display: grid; grid-template-columns: 1.2fr 0.8fr 0.6fr; gap: 0.75rem; padding: 0.8rem 1rem; border-top: 1px solid #f5f5f4; }
    .crm-market__row:first-child { border-top: 0; }
    .crm-market__name { margin: 0; font-weight: 680; }
    .crm-market__note { margin: 0.2rem 0 0; color: #78716c; font-size: 0.84rem; }
    .crm-market__field, .crm-market__pick {
        border: 1px solid #e7e5e4; border-radius: 0.65rem; background: #fff; padding: 0.45rem 0.65rem; font: inherit;
    }
    .crm-market__btn { border: 0; border-radius: 0.65rem; background: var(--primary-600, #ea580c); color: #fff; padding: 0.5rem 0.85rem; font: inherit; font-weight: 650; cursor: pointer; }
    .crm-market__label { display: block; margin-bottom: 0.25rem; font-size: 0.78rem; font-weight: 650; color: #78716c; }
    .crm-market__empty { margin: 0; padding: 1rem; color: #78716c; }
</style>
<div class="crm-market">
    <div class="crm-market__bar">
        <p class="crm-market__lead">Upload a file with a Mobile column. These people stay off the student list. The public join page is {{ $joinUrl }}.</p>
        <form wire:submit="importSheet" class="crm-market__send">
            <label>
                <span class="crm-market__label">Excel or CSV</span>
                <input type="file" wire:model="sheet" accept=".xlsx,.xls,.csv,.txt" class="crm-market__field" />
            </label>
            <button type="submit" class="crm-market__btn">Upload</button>
        </form>
    </div>
    <label class="crm-market__search">
        <span class="crm-market__label">Find</span>
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Name or number" class="crm-market__field" />
    </label>
    <section class="crm-market__sheet">
        <div class="crm-market__row">
            <strong>{{ $count }} {{ $count === 1 ? 'person' : 'people' }}</strong>
        </div>
        @forelse ($rows as $row)
            <div class="crm-market__row">
                <div>
                    <p class="crm-market__name">{{ $row->name ?: 'No name' }}</p>
                    @if (is_array($row->extra) && $row->extra !== [])
                        <p class="crm-market__note">{{ collect($row->extra)->map(fn ($value, $key) => $key.': '.$value)->implode(' · ') }}</p>
                    @endif
                </div>
                <p class="crm-market__note">{{ \App\Support\CrmAccess::canViewStudentMobile(auth()->user()) ? $row->mobile : 'Number hidden' }}</p>
                <p class="crm-market__note">{{ $row->source === 'landing' ? 'Join page' : 'File' }}</p>
            </div>
        @empty
            <p class="crm-market__empty">No people yet. Upload a file or share the join page.</p>
        @endforelse
        <div class="crm-market__send" style="padding: 0.85rem 1rem; border-top: 1px solid #f5f5f4;">
            <select wire:model.live="templateId" class="crm-market__pick" aria-label="Template">
                <option value="">Choose a template</option>
                @foreach ($templates as $template)
                    <option value="{{ $template['id'] }}">{{ $template['label'] }}</option>
                @endforeach
            </select>
            @php
                $chosen = collect($templates)->firstWhere('id', (int) ($chosenTemplateId ?? 0));
            @endphp
            @foreach ($chosen['blanks'] ?? [] as $index => $blank)
                <label>
                    <span class="crm-market__label">Blank {{ $blank }}</span>
                    <select wire:model.live="blankSources.{{ $index }}" class="crm-market__pick">
                        <option value="name">This person's name</option>
                        <option value="text">Same words for everyone</option>
                    </select>
                    @if (($blankSources[$index] ?? 'name') === 'text')
                        <input type="text" wire:model="blankText.{{ $index }}" class="crm-market__field" placeholder="Words for this blank" />
                    @endif
                </label>
            @endforeach
            <button type="button" wire:click="sendToList" class="crm-market__btn">Send to these numbers</button>
        </div>
    </section>
</div>
