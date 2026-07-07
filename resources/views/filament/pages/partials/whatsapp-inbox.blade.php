<div class="crm-wa-global-inbox">
    <div class="crm-wa-global-inbox__shell">
        <aside class="crm-wa-global-inbox__list" aria-label="Recent chats">
            <div class="crm-wa-global-inbox__list-head">
                <div>
                    <h2 class="crm-wa-global-inbox__list-title">Recent chats</h2>
                    <p class="crm-wa-global-inbox__list-sub">Parent conversations across all students</p>
                </div>
            </div>

            <div class="crm-wa-global-inbox__search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="crm-wa-global-inbox__search-icon" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name, mobile, or message…"
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
                            Sends and parent replies will appear here.
                        </p>
                    </div>
                @else
                    @foreach ($conversations as $conversation)
                        <button
                            type="button"
                            wire:click="selectConversation({{ $conversation['student_id'] }})"
                            wire:key="wa-conversation-{{ $conversation['student_id'] }}"
                            @class([
                                'crm-wa-global-inbox__item',
                                'crm-wa-global-inbox__item--active' => (int) $selectedStudentId === (int) $conversation['student_id'],
                            ])
                        >
                            <span class="crm-wa-global-inbox__avatar">
                                {{ strtoupper(substr($conversation['student_name'], 0, 1)) }}
                            </span>
                            <span class="crm-wa-global-inbox__item-body">
                                <span class="crm-wa-global-inbox__item-top">
                                    <span class="crm-wa-global-inbox__item-name">{{ $conversation['student_name'] }}</span>
                                    <span class="crm-wa-global-inbox__item-time">{{ $conversation['last_at_label'] }}</span>
                                </span>
                                <span class="crm-wa-global-inbox__item-bottom">
                                    <span class="crm-wa-global-inbox__item-preview">
                                        @if (($conversation['last_direction'] ?? '') === 'inbound')
                                            <span class="crm-wa-global-inbox__item-you">Parent:</span>
                                        @endif
                                        {{ \Illuminate\Support\Str::limit($conversation['preview'], 72) }}
                                    </span>
                                    @if ($conversation['needs_reply'] ?? false)
                                        <span class="crm-wa-global-inbox__badge">Reply</span>
                                    @elseif ($conversation['session_open'] ?? false)
                                        <span class="crm-wa-global-inbox__badge crm-wa-global-inbox__badge--open">24h</span>
                                    @endif
                                </span>
                                <span class="crm-wa-global-inbox__item-phone">{{ $conversation['phone_display'] }}</span>
                            </span>
                        </button>
                    @endforeach
                @endif
            </div>
        </aside>

        <section class="crm-wa-global-inbox__chat" aria-label="Selected conversation">
            @if (! $selectedStudentId || ! $chatStudent)
                <div class="crm-wa-global-inbox__placeholder">
                    <div class="crm-wa-global-inbox__placeholder-icon">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-8 w-8" />
                    </div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">Select a chat</p>
                    <p class="mt-2 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                        Pick a parent conversation on the left to read messages, send templates, or reply within the 24-hour window.
                    </p>
                </div>
            @else
                <div class="crm-wa-global-inbox__chat-head">
                    <div>
                        <p class="crm-wa-global-inbox__chat-name">{{ $chatStudent->name }}</p>
                        <p class="crm-wa-global-inbox__chat-phone">{{ $chatStudent->mobile }}</p>
                    </div>
                    <a
                        href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $chatStudent->id]).'?tab=messages' }}"
                        class="crm-wa-global-inbox__profile-link"
                    >
                        Open student profile
                    </a>
                </div>

                @include('filament.pages.partials.student-profile-messages', $messagesViewData ?? [])
            @endif
        </section>
    </div>
</div>
