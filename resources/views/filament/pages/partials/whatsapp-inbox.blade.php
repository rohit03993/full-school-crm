@php
    $chatOpen = filled($selectedPhone) || filled($selectedStudentId);
@endphp

<div
    x-data="{ chatOpen: @js($chatOpen), activePhone: @js($selectedPhone ?? '') }"
    x-on:whatsapp-chat-opened="chatOpen = true; activePhone = ($event.detail.phone || '')"
    x-on:whatsapp-chat-closed="chatOpen = false; activePhone = ''"
    :class="{ 'crm-wa-global-inbox--chat-open': chatOpen }"
    @class([
        'crm-wa-global-inbox',
        'crm-wa-global-inbox--chat-open' => $chatOpen,
    ])
    wire:poll.10s.visible="pollInbox"
>
    <div
        :class="{ 'crm-wa-global-inbox__shell--chat-open': chatOpen }"
        @class([
            'crm-wa-global-inbox__shell',
            'crm-wa-global-inbox__shell--chat-open' => $chatOpen,
        ])
    >
        <aside class="crm-wa-global-inbox__list" aria-label="Recent chats">
            <div class="crm-wa-global-inbox__list-head">
                <div class="crm-wa-global-inbox__list-copy">
                    <h2 class="crm-wa-global-inbox__list-title">Chats</h2>
                    <p class="crm-wa-global-inbox__list-sub">
                        @if ($inboxLoaded)
                            @if (($listFilter ?? 'all') === 'pending')
                                {{ (int) ($pendingReplyCount ?? 0) }} waiting for a reply
                            @elseif (($listFilter ?? 'all') === 'failed')
                                {{ (int) ($failedSendCount ?? 0) }} last school send{{ ((int) ($failedSendCount ?? 0)) === 1 ? '' : 's' }} failed
                            @else
                                {{ (int) ($totalConversationCount ?? count($conversations)) }} conversation{{ ((int) ($totalConversationCount ?? count($conversations))) === 1 ? '' : 's' }}
                                @if ((int) ($pendingReplyCount ?? 0) > 0)
                                    · {{ (int) $pendingReplyCount }} waiting
                                @endif
                                @if ((int) ($failedSendCount ?? 0) > 0)
                                    · {{ (int) $failedSendCount }} failed
                                @endif
                            @endif
                        @else
                            Loading…
                        @endif
                    </p>
                </div>

                <div class="crm-wa-global-inbox__filters" role="tablist" aria-label="Chat filters">
                    <button
                        type="button"
                        wire:click="setListFilter('all')"
                        role="tab"
                        aria-selected="{{ ($listFilter ?? 'all') === 'all' ? 'true' : 'false' }}"
                        @class([
                            'crm-wa-global-inbox__filter',
                            'crm-wa-global-inbox__filter--active' => ($listFilter ?? 'all') === 'all',
                        ])
                    >
                        All
                    </button>
                    <button
                        type="button"
                        wire:click="setListFilter('pending')"
                        role="tab"
                        aria-selected="{{ ($listFilter ?? 'all') === 'pending' ? 'true' : 'false' }}"
                        @class([
                            'crm-wa-global-inbox__filter',
                            'crm-wa-global-inbox__filter--active' => ($listFilter ?? 'all') === 'pending',
                        ])
                    >
                        Reply pending
                        @if ((int) ($pendingReplyCount ?? 0) > 0)
                            <span class="crm-wa-global-inbox__filter-count">{{ (int) $pendingReplyCount }}</span>
                        @endif
                    </button>
                    <button
                        type="button"
                        wire:click="setListFilter('failed')"
                        role="tab"
                        aria-selected="{{ ($listFilter ?? 'all') === 'failed' ? 'true' : 'false' }}"
                        @class([
                            'crm-wa-global-inbox__filter',
                            'crm-wa-global-inbox__filter--active' => ($listFilter ?? 'all') === 'failed',
                        ])
                    >
                        Failed
                        @if ((int) ($failedSendCount ?? 0) > 0)
                            <span class="crm-wa-global-inbox__filter-count crm-wa-global-inbox__filter-count--failed">{{ (int) $failedSendCount }}</span>
                        @endif
                    </button>
                </div>
            </div>

            <div class="crm-wa-global-inbox__search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="crm-wa-global-inbox__search-icon" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name or mobile…"
                    class="crm-wa-global-inbox__search-input"
                />
            </div>

            <div class="crm-wa-global-inbox__items">
                @if (! $inboxLoaded)
                    <p class="crm-wa-global-inbox__empty">Loading chats…</p>
                @elseif ($conversations === [])
                    <div class="crm-wa-global-inbox__empty">
                        @if (($listFilter ?? 'all') === 'pending')
                            <p class="font-medium text-gray-800 dark:text-gray-200">No chats waiting for a reply</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                The last message in every chat is from the school.
                            </p>
                        @elseif (($listFilter ?? 'all') === 'failed')
                            <p class="font-medium text-gray-800 dark:text-gray-200">No failed sends</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                The last school message to each number reached WhatsApp.
                            </p>
                        @else
                            <p class="font-medium text-gray-800 dark:text-gray-200">No conversations yet</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Sends and replies will appear here.
                            </p>
                        @endif
                    </div>
                @else
                    @foreach ($conversations as $conversation)
                        @php
                            $contactTags = $conversation['contact_tags'] ?? [];
                        @endphp
                        <button
                            type="button"
                            x-on:click="activePhone = {{ \Illuminate\Support\Js::from((string) ($conversation['phone'] ?? '')) }}; chatOpen = true; Livewire.dispatch('open-whatsapp-chat', {{ \Illuminate\Support\Js::from(['phone' => (string) ($conversation['phone'] ?? ''), 'studentId' => $conversation['student_id'] ?? null]) }})"
                            wire:key="wa-conversation-{{ $conversation['phone'] }}"
                            class="crm-wa-global-inbox__item"
                            :class="{ 'crm-wa-global-inbox__item--active': activePhone === {{ \Illuminate\Support\Js::from((string) ($conversation['phone'] ?? '')) }} }"
                        >
                            <span class="crm-wa-global-inbox__avatar">
                                {{ strtoupper(substr($conversation['student_name'], 0, 1)) }}
                            </span>
                            <span class="crm-wa-global-inbox__item-body">
                                <span class="crm-wa-global-inbox__item-top">
                                    <span class="crm-wa-global-inbox__item-name">
                                        <span class="crm-wa-global-inbox__item-name-text">{{ $conversation['student_name'] }}</span>
                                        @foreach ($contactTags as $tag)
                                            <span @class([
                                                'crm-wa-contact-tag',
                                                'crm-wa-contact-tag--staff' => $tag === 'Staff',
                                                'crm-wa-contact-tag--student' => $tag === 'Student',
                                                'crm-wa-contact-tag--lead' => $tag === 'Lead',
                                            ])>{{ $tag }}</span>
                                        @endforeach
                                        @unless ($conversation['is_linked'] ?? true)
                                            <span class="crm-wa-global-inbox__item-new">New</span>
                                        @endunless
                                    </span>
                                    <span class="crm-wa-global-inbox__item-time">{{ $conversation['last_at_label'] }}</span>
                                </span>
                                <span class="crm-wa-global-inbox__item-bottom">
                                    <span class="crm-wa-global-inbox__item-preview">
                                        @if (($conversation['last_direction'] ?? '') === 'outbound')
                                            <span class="crm-wa-global-inbox__item-you">You:</span>
                                        @endif
                                        {{ \Illuminate\Support\Str::limit($conversation['preview'], 64) }}
                                    </span>
                                    @if ($conversation['needs_reply'] ?? false)
                                        <span class="crm-wa-global-inbox__badge">Reply</span>
                                    @elseif ($conversation['last_send_failed'] ?? false)
                                        <span class="crm-wa-global-inbox__badge crm-wa-global-inbox__badge--failed">Failed</span>
                                    @elseif ($conversation['session_open'] ?? false)
                                        <span class="crm-wa-global-inbox__badge crm-wa-global-inbox__badge--open">24h</span>
                                    @endif
                                </span>
                            </span>
                        </button>
                    @endforeach
                @endif
            </div>
        </aside>

        <livewire:whatsapp-inbox-thread
            :phone="$selectedPhone"
            :student-id="$selectedStudentId"
            wire:key="whatsapp-inbox-thread"
        />
    </div>
</div>
