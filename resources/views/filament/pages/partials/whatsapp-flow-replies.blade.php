@php
    $rows = $rows ?? [];
    $count = (int) ($count ?? 0);
    $shown = count($rows);
@endphp
<section class="crm-flow__sheet">
    <div class="crm-flow__detail">
        <div>
            <h2 class="crm-flow__name">{{ $count }} {{ $count === 1 ? 'reply' : 'replies' }}</h2>
            @if ($shown !== $count)
                <p class="crm-flow__note">Showing the latest {{ $shown }}. Search to narrow the list.</p>
            @endif
        </div>
        <label class="crm-flow__search">
            <span class="crm-flow__label">Find a reply</span>
            <input type="search" wire:model.live.debounce.400ms="replySearch" placeholder="Name or number" class="crm-flow__field" />
        </label>
    </div>
    @if ($rows === [])
        <p class="crm-flow__empty">No replies for this flow yet.</p>
    @else
        @foreach ($rows as $reply)
            <div class="crm-flow__person">
                <p class="crm-flow__name">
                    @if ($reply['profile_url'])
                        <a href="{{ $reply['profile_url'] }}" class="crm-flow__link">{{ $reply['name'] }}</a>
                    @else
                        {{ $reply['name'] }}
                    @endif
                </p>
                <p class="crm-flow__note">
                    @if (\App\Support\CrmAccess::canViewStudentMobile(auth()->user()))
                        {{ $reply['mobile'] !== '' ? $reply['mobile'] : 'No number' }}
                    @else
                        Number hidden
                    @endif
                    · {{ $reply['when'] }}
                </p>
                @if ($reply['answers'] !== '')
                    <p class="crm-flow__answers">{{ $reply['answers'] }}</p>
                @endif
            </div>
        @endforeach
        <div class="crm-flow__send">
            <select wire:model="sendTemplateIds.{{ $flowKey }}" class="crm-flow__pick" aria-label="Template for this flow">
                <option value="">Choose a template</option>
                @foreach ($templates ?? [] as $template)
                    <option value="{{ $template['id'] }}">{{ $template['label'] }}</option>
                @endforeach
            </select>
            <button type="button" wire:click="sendReplyTemplate('{{ $flowKey }}')" class="crm-flow__btn">Send to these numbers</button>
        </div>
        @if (($templates ?? []) === [])
            <p class="crm-flow__locked" style="padding: 0 1.15rem 1rem;">No approved template without blanks. Sync templates in WhatsApp setup first.</p>
        @endif
    @endif
</section>
