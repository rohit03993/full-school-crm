@php
    $rows = $rows ?? [];
    $count = (int) ($count ?? 0);
    $shown = count($rows);
@endphp
<div class="crm-flow__replies">
    <p class="crm-flow__meta">{{ $count }} {{ $count === 1 ? 'reply' : 'replies' }}@if ($shown !== $count) · showing {{ $shown }}@endif</p>
    @if ($rows === [])
        <p class="crm-flow__empty">No replies for this flow yet.</p>
    @else
        @foreach ($rows as $reply)
            <div class="crm-flow__row">
                <div>
                    <p class="crm-flow__title">
                        @if ($reply['profile_url'])
                            <a href="{{ $reply['profile_url'] }}" class="crm-flow__edit">{{ $reply['name'] }}</a>
                        @else
                            {{ $reply['name'] }}
                        @endif
                    </p>
                    <p class="crm-flow__meta">
                        @if (\App\Support\CrmAccess::canViewStudentMobile(auth()->user()))
                            {{ $reply['mobile'] !== '' ? $reply['mobile'] : 'No number' }}
                        @else
                            Number hidden
                        @endif
                        {{ $reply['when'] }}
                    </p>
                    @if ($reply['answers'] !== '')
                        <p class="crm-flow__answers">{{ $reply['answers'] }}</p>
                    @endif
                </div>
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
            <p class="crm-flow__locked">No approved template without blanks. Sync templates in WhatsApp setup, or pick one that has no name or date to fill in.</p>
        @endif
    @endif
</div>
