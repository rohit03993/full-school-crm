<section class="crm-wa-global-inbox__chat" aria-label="Selected conversation" @if ($chatOpen) wire:poll.10s.visible="pollThread" @endif>
    <div wire:loading.flex wire:target="openChatFromList,openChat" class="crm-wa-global-inbox__placeholder">
        <p class="text-sm text-gray-500 dark:text-gray-400">Opening chat…</p>
    </div>

    @if (! $chatOpen)
        <div wire:loading.remove wire:target="openChatFromList,openChat" class="crm-wa-global-inbox__placeholder crm-wa-global-inbox__placeholder--desktop">
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
            <div wire:loading.remove wire:target="openChatFromList,openChat" class="crm-wa-global-inbox__chat-head">
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
                        @if (\App\Support\CrmAccess::canViewStudentMobile(auth()->user()))
                            {{ $chatStudent?->mobile ?? $selectedPhone }}
                        @else
                            Hidden
                        @endif
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
                <div wire:loading.remove wire:target="openChatFromList,openChat" class="crm-wa-global-inbox__placeholder">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Loading conversation…</p>
                </div>
            @endif
    @endif
</section>
