<div @class(['crm-wa-inbox', 'crm-wa-inbox--compact' => $compactInbox ?? false])>
    @php
        $lastThreadKey = $messageThread === []
            ? 'empty'
            : ($messageThread[array_key_last($messageThread)]['key'] ?? 'empty');
        $hasPendingMedia = collect($messageThread)->contains(
            fn (array $message): bool => (bool) ($message['mediaPending'] ?? false),
        );
        $replyFieldId = ($compactInbox ?? false) ? 'wa-quick-reply-inbox' : 'wa-quick-reply-profile';
        $templateFieldId = ($compactInbox ?? false) ? 'wa-template-select-inbox' : 'wa-template-select-profile';
        $attachmentFieldId = ($compactInbox ?? false) ? 'wa-quick-attachment-inbox' : 'wa-quick-attachment-profile';
    @endphp

    @unless ($compactInbox ?? false)
    <header class="crm-wa-inbox__toolbar">
        <div class="crm-wa-inbox__toolbar-main">
            <div class="crm-wa-inbox__toolbar-copy">
                <p class="crm-wa-inbox__title">WhatsApp with parent</p>
                <p class="crm-wa-inbox__subtitle">
                    <span class="crm-wa-inbox__phone">{{ $record?->mobile ?? $chatPhone ?? 'No mobile on file' }}</span>
                </p>
            </div>
            @if ($metaRoutingActive)
                <span @class([
                    'crm-wa-pill',
                    'crm-wa-pill--open' => $metaSessionOpen,
                    'crm-wa-pill--closed' => ! $metaSessionOpen,
                ])>
                    {{ $metaSessionOpen ? '24h window open' : 'Templates only' }}
                </span>
            @endif
        </div>
        @if ($metaRoutingActive && $metaSessionOpen)
            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--success">
                Parent messaged recently — type a reply below or send a template.
            </p>
        @elseif ($metaRoutingActive && ! $metaSessionOpen)
            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--banner">
                Outside the 24-hour window — send an approved <strong>template</strong> until the parent messages again.
            </p>
        @endif
        @if (filled($waTemplateSyncHint))
            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--banner">{{ $waTemplateSyncHint }}</p>
        @endif
    </header>
    @endunless

    <div @class(['crm-wa-inbox__layout', 'crm-wa-inbox__layout--compact' => $compactInbox ?? false])>
        <section
            class="crm-wa-inbox__thread"
            wire:key="wa-thread-{{ count($messageThread) }}-{{ $lastThreadKey }}"
            @if ($hasPendingMedia)
                wire:poll.8s="refreshThreadMedia"
            @endif
            aria-label="WhatsApp conversation"
        >
            @unless ($compactInbox ?? false)
            <div class="crm-wa-inbox__thread-head">
                <h3 class="crm-wa-inbox__thread-title">Conversation</h3>
                <p class="crm-wa-inbox__thread-meta">{{ count($messageThread) }} messages</p>
            </div>
            @endunless

            @if (! $messagesTabLoaded)
                <p class="crm-wa-inbox__hint crm-wa-inbox__hint--center">Loading…</p>
            @elseif ($messageThread === [])
                <div class="crm-wa-inbox__empty">
                    <p class="font-medium text-gray-700 dark:text-gray-200">No messages yet</p>
                    <p class="crm-wa-inbox__hint">Sends and parent replies appear here.</p>
                </div>
            @else
                <div
                    class="crm-wa-chat"
                    x-data
                    x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
                >
                    @foreach ($messageThread as $message)
                        <div @class([
                            'crm-wa-bubble',
                            'crm-wa-bubble--in' => ($message['direction'] ?? '') === 'inbound',
                            'crm-wa-bubble--out' => ($message['direction'] ?? '') === 'outbound',
                            'crm-wa-bubble--failed' => ($message['status'] ?? '') === 'failed',
                        ])>
                            <div class="crm-wa-bubble__meta">
                                <span>{{ ($message['direction'] ?? '') === 'inbound' ? 'Parent' : 'You' }}</span>
                                <span>{{ $message['at_label'] ?? '' }}</span>
                            </div>
                            @include('filament.pages.partials.whatsapp-message-bubble', ['message' => $message])
                            @if (! empty($message['errorMessage']))
                                <p class="crm-wa-bubble__error">{{ $message['errorMessage'] }}</p>
                            @endif
                            <div class="crm-wa-bubble__footer">
                                <span @class([
                                    'crm-wa-status',
                                    'crm-wa-status--ok' => in_array($message['status'] ?? '', ['sent', 'delivered', 'read', 'received'], true),
                                    'crm-wa-status--warn' => in_array($message['status'] ?? '', ['pending', 'processing', 'queued'], true),
                                    'crm-wa-status--bad' => ($message['status'] ?? '') === 'failed',
                                ])>
                                    {{ $message['statusLabel'] ?? '' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <footer class="crm-wa-inbox__compose" aria-label="Send messages">
            <section class="crm-wa-inbox__composer crm-wa-inbox__composer--template">
                <div class="crm-wa-inbox__composer-head crm-wa-inbox__composer-head--compact">
                    <h3 class="crm-wa-inbox__composer-title">Template</h3>
                </div>

                @if (! $record && filled($chatPhone ?? null))
                    <p class="crm-wa-inbox__hint">
                        Unknown number — reply freely while the 24h window is open. Add them as a student to send templates.
                    </p>
                @elseif (blank($record?->mobile) && blank($chatPhone ?? null))
                    <p class="crm-wa-inbox__hint crm-wa-inbox__hint--danger">Add a mobile number on the student profile first.</p>
                @elseif ($waTemplates->isEmpty())
                    <p class="crm-wa-inbox__hint">
                        No templates synced.
                        Open <strong>{{ \App\Support\CrmNavigation::whatsAppMenu('Connection & Setup') }}</strong> → <strong>Sync templates</strong>.
                    </p>
                @else
                    <div class="crm-wa-inbox__template-row">
                        <div class="crm-wa-inbox__field crm-wa-inbox__field--template">
                            <label class="crm-wa-inbox__label sr-only" for="{{ $templateFieldId }}">Template</label>
                            <x-crm.select
                                id="{{ $templateFieldId }}"
                                wire:model.live="sendWhatsAppTemplateId"
                            >
                                <option value="">Choose template…</option>
                                @foreach ($waTemplates as $template)
                                    <option value="{{ $template->id }}">
                                        {{ $template->name }}
                                        @if ((int) $template->param_count > 0)
                                            — {{ (int) $template->param_count }} {{ (int) $template->param_count === 1 ? 'variable' : 'variables' }}
                                        @endif
                                    </option>
                                @endforeach
                            </x-crm.select>
                        </div>

                        @if ($sendWhatsAppTemplateId)
                            <button
                                type="button"
                                wire:click="sendWhatsAppMessage"
                                wire:loading.attr="disabled"
                                wire:target="sendWhatsAppMessage"
                                class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--template"
                            >
                                <span wire:loading.remove wire:target="sendWhatsAppMessage">Send template</span>
                                <span wire:loading wire:target="sendWhatsAppMessage">Sending…</span>
                            </button>
                        @endif
                    </div>

                    @if ($sendWhatsAppTemplateId)
                        @if ($sendWhatsAppTemplateParamCount > 0)
                            <div class="crm-wa-inbox__param-grid">
                                @foreach ($sendWhatsAppTemplateFields as $field)
                                    <div class="crm-wa-inbox__field crm-wa-inbox__field--param" wire:key="wa-param-{{ $field['index'] }}">
                                        <label class="crm-wa-inbox__label" for="wa-param-{{ ($compactInbox ?? false) ? 'inbox' : 'profile' }}-{{ $field['index'] }}">
                                            {{ $field['label'] }}
                                            <span class="crm-wa-inbox__required" aria-hidden="true">*</span>
                                        </label>
                                        <input
                                            id="wa-param-{{ ($compactInbox ?? false) ? 'inbox' : 'profile' }}-{{ $field['index'] }}"
                                            type="text"
                                            wire:model.live="sendWhatsAppTemplateParams.{{ $field['index'] }}"
                                            placeholder="{{ $field['placeholder'] }}"
                                            class="crm-wa-inbox__input"
                                            required
                                        />
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (filled($sendWhatsAppTemplatePreview))
                            <div class="crm-wa-inbox__preview crm-wa-inbox__preview--muted">
                                <p class="crm-wa-inbox__preview-body">{{ $sendWhatsAppTemplatePreview }}</p>
                            </div>
                        @endif
                    @endif
                @endif
            </section>

            @if ($metaRoutingActive && $metaSessionOpen)
                <section class="crm-wa-inbox__composer crm-wa-inbox__composer--reply">
                    @unless ($compactInbox ?? false)
                    @if ($showMetaReplyAttachment ?? false)
                        <div class="crm-wa-inbox__field">
                            <label class="crm-wa-inbox__label" for="{{ $attachmentFieldId }}">Photo, video, or file</label>
                            <input
                                id="{{ $attachmentFieldId }}"
                                type="file"
                                wire:model="metaReplyAttachment"
                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx"
                                class="crm-wa-inbox__file"
                            />
                            <div wire:loading wire:target="metaReplyAttachment,sendMetaMedia" class="crm-wa-inbox__hint">Uploading…</div>
                        </div>
                    @endif
                    @endunless

                    <div class="crm-wa-inbox__reply-bar">
                        @unless ($compactInbox ?? false)
                        @if (! ($showMetaReplyAttachment ?? false))
                            <button
                                type="button"
                                wire:click="enableMetaReplyAttachment"
                                class="crm-wa-inbox__icon-btn"
                                title="Attach photo or file"
                            >
                                <x-filament::icon icon="heroicon-o-paper-clip" class="h-5 w-5" />
                            </button>
                        @endif
                        @else
                        <p class="crm-wa-inbox__hint crm-wa-inbox__hint--inline">
                            Files: use <strong>Open student profile</strong> → Messages.
                        </p>
                        @endunless

                        <div class="crm-wa-inbox__field crm-wa-inbox__field--reply">
                            <label class="crm-wa-inbox__label sr-only" for="{{ $replyFieldId }}">Message</label>
                            <textarea
                                id="{{ $replyFieldId }}"
                                wire:model.live="metaReplyText"
                                rows="1"
                                class="crm-wa-inbox__textarea crm-wa-inbox__textarea--reply"
                                placeholder="Type a message…"
                            ></textarea>
                        </div>

                        @if (filled($metaReplyAttachment ?? null))
                            <button
                                type="button"
                                wire:click="sendMetaMedia"
                                wire:loading.attr="disabled"
                                wire:target="sendMetaMedia,metaReplyAttachment"
                                class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--reply crm-wa-inbox__send-btn--icon"
                                title="Send attachment"
                            >
                                <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5" />
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="sendMetaReply"
                                wire:loading.attr="disabled"
                                wire:target="sendMetaReply"
                                class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--reply crm-wa-inbox__send-btn--icon"
                                title="Send reply"
                            >
                                <span wire:loading.remove wire:target="sendMetaReply">
                                    <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5" />
                                </span>
                                <span wire:loading wire:target="sendMetaReply">…</span>
                            </button>
                        @endif
                    </div>
                </section>
            @endif
        </footer>
    </div>
</div>
