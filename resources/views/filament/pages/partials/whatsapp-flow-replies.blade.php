@php
    $rows = $rows ?? [];
    $count = (int) ($count ?? 0);
    $shown = count($rows);
    $initial = function (string $name): string {
        $letter = mb_strtoupper(mb_substr(trim($name), 0, 1));

        return $letter !== '' ? $letter : '?';
    };
@endphp
<section class="crm-flow__sheet">
    <div class="crm-flow__detail">
        <div>
            <h2 class="crm-flow__name">Replies</h2>
            <p class="crm-flow__note">
                {{ $count }} {{ $count === 1 ? 'person' : 'people' }}
                @if ($shown !== $count)
                    · showing the latest {{ $shown }}
                @endif
            </p>
        </div>
        <label class="crm-flow__search">
            <span class="crm-flow__label">Find a reply</span>
            <input type="search" wire:model.live.debounce.400ms="replySearch" placeholder="Name or number" class="crm-flow__field" />
        </label>
    </div>
    @if ($rows === [])
        <p class="crm-flow__empty">No replies for this flow yet. When someone finishes it, their name and answers show here.</p>
    @else
        @foreach ($rows as $reply)
            <div class="crm-flow__person">
                <span class="crm-flow__avatar">{{ $initial($reply['name']) }}</span>
                <div>
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
                    </p>
                    @if ($reply['answers'] !== '')
                        <p class="crm-flow__answers">{{ $reply['answers'] }}</p>
                    @endif
                </div>
                <span class="crm-flow__when">{{ $reply['when'] }}</span>
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
