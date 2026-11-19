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
            <select wire:model.live="sendTemplateIds.{{ $flowKey }}" class="crm-flow__pick" aria-label="Template for this flow">
                <option value="">Choose a template</option>
                @foreach ($templates ?? [] as $template)
                    <option value="{{ $template['id'] }}">{{ $template['label'] }}</option>
                @endforeach
            </select>
            @php
                $chosen = collect($templates ?? [])->firstWhere('id', (int) ($chosenTemplateId ?? 0));
            @endphp
            @foreach ($chosen['blanks'] ?? [] as $index => $blank)
                <label class="crm-flow__search">
                    <span class="crm-flow__label">Blank {{ $blank }}</span>
                    <select wire:model.live="sendBlankSources.{{ $flowKey }}.{{ $index }}" class="crm-flow__pick">
                        <option value="name">This person's name</option>
                        <option value="text">Same words for everyone</option>
                    </select>
                    @if (($blankSources[$index] ?? 'name') === 'text')
                        <input type="text" wire:model="sendBlankText.{{ $flowKey }}.{{ $index }}" class="crm-flow__field" placeholder="Words for this blank" />
                    @endif
                </label>
            @endforeach
            <button type="button" wire:click="sendReplyTemplate('{{ $flowKey }}')" class="crm-flow__btn">Send to these numbers</button>
        </div>
        @if (($templates ?? []) === [])
            <p class="crm-flow__locked" style="padding: 0 1.15rem 1rem;">No approved template without a photo. Sync templates in WhatsApp setup first.</p>
        @endif
    @endif
</section>

@php
    $waiting = $unfinished ?? [];
    $waitingCount = (int) ($unfinishedCount ?? 0);
@endphp
<section class="crm-flow__sheet">
    <div class="crm-flow__detail">
        <div>
            <h2 class="crm-flow__name">Sent, not finished</h2>
            <p class="crm-flow__note">{{ $waitingCount }} {{ $waitingCount === 1 ? 'person was' : 'people were' }} sent this form and did not submit. WhatsApp does not tell us if they opened it.</p>
        </div>
    </div>
    @if ($waiting === [])
        <p class="crm-flow__empty">No one is waiting on this flow.</p>
    @else
        @foreach ($waiting as $person)
            <div class="crm-flow__person">
                <span class="crm-flow__avatar">{{ $initial($person['name']) }}</span>
                <div>
                    <p class="crm-flow__name">
                        @if ($person['profile_url'])
                            <a href="{{ $person['profile_url'] }}" class="crm-flow__link">{{ $person['name'] }}</a>
                        @else
                            {{ $person['name'] }}
                        @endif
                    </p>
                    <p class="crm-flow__note">
                        @if (\App\Support\CrmAccess::canViewStudentMobile(auth()->user()))
                            {{ $person['mobile'] !== '' ? $person['mobile'] : 'No number' }}
                        @else
                            Number hidden
                        @endif
                    </p>
                </div>
                <span class="crm-flow__when">{{ $person['when'] }}</span>
            </div>
        @endforeach
    @endif
</section>
