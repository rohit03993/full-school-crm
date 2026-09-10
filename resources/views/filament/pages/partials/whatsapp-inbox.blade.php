@php
    $chatOpen = filled($selectedPhone) || filled($selectedStudentId);
@endphp

<div @class([
    'crm-wa-global-inbox',
    'crm-wa-global-inbox--chat-open' => $chatOpen,
])>
    <div @class([
        'crm-wa-global-inbox__shell',
        'crm-wa-global-inbox__shell--chat-open' => $chatOpen,
    ])>
        <aside class="crm-wa-global-inbox__list" aria-label="Recent chats">
            <div class="crm-wa-global-inbox__list-head">
                <div>
                    <h2 class="crm-wa-global-inbox__list-title">Chats</h2>
                    <p class="crm-wa-global-inbox__list-sub">
                        @if ($inboxLoaded)
                            {{ count($conversations) }} conversation{{ count($conversations) === 1 ? '' : 's' }}
                        @else
                            Loading…
                        @endif
                    </p>
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
                        <p class="font-medium text-gray-800 dark:text-gray-200">No conversations yet</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Sends and replies will appear here.
                        </p>
                    </div>
                @else
                    @foreach ($conversations as $conversation)
                        @php
                            $isActive = filled($selectedPhone)
                                && (string) $selectedPhone === (string) ($conversation['phone'] ?? '');
                            $contactTags = $conversation['contact_tags'] ?? [];
                        @endphp
                        <button
                            type="button"
                            wire:click="selectConversation({{ \Illuminate\Support\Js::from($conversation['phone']) }}{{ filled($conversation['student_id'] ?? null) ? ', '.(int) $conversation['student_id'] : '' }})"
                            wire:key="wa-conversation-{{ $conversation['phone'] }}"
                            @class([
                                'crm-wa-global-inbox__item',
                                'crm-wa-global-inbox__item--active' => $isActive,
                            ])
                        >
                            <span class="crm-wa-global-inbox__avatar">
                                {{ strtoupper(substr($conversation['student_name'], 0, 1)) }}
                            </span>
                            <span class="crm-wa-global-inbox__item-body">
                                <span class="crm-wa-global-inbox__item-top">
                                    <span class="crm-wa-global-inbox__item-name">
                                        {{ $conversation['student_name'] }}
                                        @foreach ($contactTags as $tag)
                                            <span @class([
                                                'crm-wa-contact-tag',
                                                'crm-wa-contact-tag--staff' => $tag === 'Staff',
                                                'crm-wa-contact-tag--student' => $tag === 'Student',
                                                'crm-wa-contact-tag--lead' => $tag === 'Lead',
                                            ])>{{ $tag }}</span>
                                        @endforeach
                                        @unless ($conversation['is_linked'] ?? true)
                                            <span class="ml-1 rounded bg-amber-500/15 px-1 py-0.5 text-[9px] font-bold uppercase text-amber-800 dark:text-amber-200">New</span>
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

        <section class="crm-wa-global-inbox__chat" aria-label="Selected conversation">
            @if (! $chatOpen)
                <div class="crm-wa-global-inbox__placeholder crm-wa-global-inbox__placeholder--desktop">
                    <div class="crm-wa-global-inbox__placeholder-icon">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-8 w-8" />
                    </div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">Select a chat</p>
                    <p class="mt-2 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                        Pick a conversation to read messages or reply within the 24-hour window.
                    </p>
                </div>
            @else
                @php
                    $chatContact = $chatContact ?? \App\Support\WhatsAppInboxContact::unknown();
                    $headerName = $chatContact->isLinked()
                        ? $chatContact->displayName
                        : ($chatStudent?->name ?? 'Unknown contact');
                    $headerTags = $chatContact->tagLabels();
                    $headerInitial = strtoupper(substr($headerName, 0, 1));
                @endphp
                <div class="crm-wa-global-inbox__chat-head">
                    <button
                        type="button"
                        wire:click="clearConversation"
                        class="crm-wa-global-inbox__back"
                        aria-label="Back to chats"
                    >
                        <x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" />
                    </button>

                    <span class="crm-wa-global-inbox__chat-avatar" aria-hidden="true">{{ $headerInitial }}</span>

                    <div class="crm-wa-global-inbox__chat-head-main">
                        <p class="crm-wa-global-inbox__chat-name">
                            {{ $headerName }}
                            @foreach ($headerTags as $tag)
                                <span @class([
                                    'crm-wa-contact-tag',
                                    'crm-wa-contact-tag--staff' => $tag === 'Staff',
                                    'crm-wa-contact-tag--student' => $tag === 'Student',
                                    'crm-wa-contact-tag--lead' => $tag === 'Lead',
                                ])>{{ $tag }}</span>
                            @endforeach
                        </p>
                        <p class="crm-wa-global-inbox__chat-phone">
                            {{ $chatStudent?->mobile ?? $selectedPhone }}
                            @if ($metaRoutingActive ?? false)
                                · {{ ($metaSessionOpen ?? false) ? '24h open' : 'Templates only' }}
                            @endif
                        </p>
                    </div>

                    @if ($chatStudent)
                        <a
                            href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $chatStudent->id]).'?tab=messages' }}"
                            class="crm-wa-global-inbox__profile-link"
                        >
                            Profile
                        </a>
                    @elseif (($chatContact->kind->value ?? '') === 'staff')
                        <span class="crm-wa-global-inbox__profile-link crm-wa-global-inbox__profile-link--muted">
                            Staff
                        </span>
                    @else
                        <a
                            href="{{ \App\Filament\Resources\Students\StudentResource::getUrl('index').'?action=addStudent' }}"
                            class="crm-wa-global-inbox__profile-link"
                        >
                            Add
                        </a>
                    @endif
                </div>

                @if ($messagesViewData)
                    @include('filament.pages.partials.student-profile-messages', $messagesViewData)
                @else
                    <div class="crm-wa-global-inbox__placeholder">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Loading conversation…</p>
                    </div>
                @endif
            @endif
        </section>
    </div>
</div>
