<div
    wire:init="loadMessagesTab"
    @class(['crm-wa-inbox', 'crm-wa-inbox--compact' => $compactInbox ?? false])
>
    @php
        $lastThreadKey = $messageThread === []
            ? 'empty'
            : ($messageThread[array_key_last($messageThread)]['key'] ?? 'empty');
    @endphp

    @unless ($compactInbox ?? false)
    <header class="crm-wa-inbox__toolbar">
        <div class="crm-wa-inbox__toolbar-main">
            <div class="crm-wa-inbox__toolbar-copy">
                <p class="crm-wa-inbox__title">WhatsApp with parent</p>
                <p class="crm-wa-inbox__subtitle">
                    <span class="crm-wa-inbox__phone">{{ $record->mobile ?? 'No mobile on file' }}</span>
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
                Parent messaged recently — you can send a <strong>free-text reply</strong> below, or a template anytime.
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
        <aside class="crm-wa-inbox__compose">
            <section class="crm-wa-inbox__composer">
                <div class="crm-wa-inbox__composer-head">
                    <h3 class="crm-wa-inbox__composer-title">Send template</h3>
                    <p class="crm-wa-inbox__composer-lead">Pick a template. If it has variables, fill every field marked required.</p>
                </div>

                @if (blank($record->mobile))
                    <p class="crm-wa-inbox__hint crm-wa-inbox__hint--danger">Add a mobile number on the student profile first.</p>
                @elseif ($waTemplates->isEmpty())
                    <p class="crm-wa-inbox__hint">
                        No templates synced.
                        Open <strong>{{ \App\Support\CrmNavigation::whatsAppMenu('Connection & Setup') }}</strong> → <strong>Sync templates</strong>.
                    </p>
                @else
                    <div class="crm-wa-inbox__field">
                        <label class="crm-wa-inbox__label" for="wa-template-select">Template</label>
                        <x-crm.select
                            id="wa-template-select"
                            wire:model.live="sendWhatsAppTemplateId"
                            class="mt-1"
                        >
                            <option value="">Choose template…</option>
                            @foreach ($waTemplates as $template)
                                <option value="{{ $template->id }}">
                                    {{ $template->name }}
                                    @if ((int) $template->param_count > 0)
                                        — {{ (int) $template->param_count }} {{ (int) $template->param_count === 1 ? 'variable' : 'variables' }}
                                    @else
                                        — no variables
                                    @endif
                                </option>
                            @endforeach
                        </x-crm.select>
                    </div>

                    @if ($sendWhatsAppTemplateId)
                        @if ($sendWhatsAppTemplateParamCount === 0)
                            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--info">
                                No variables needed — click send when ready.
                            </p>
                        @else
                            <p class="crm-wa-inbox__param-intro">
                                This template needs <strong>{{ $sendWhatsAppTemplateParamCount }}</strong>
                                {{ $sendWhatsAppTemplateParamCount === 1 ? 'value' : 'values' }}:
                            </p>
                            <div class="crm-wa-inbox__param-grid">
                                @foreach ($sendWhatsAppTemplateFields as $field)
                                    <div class="crm-wa-inbox__field crm-wa-inbox__field--param" wire:key="wa-param-{{ $field['index'] }}">
                                        <label class="crm-wa-inbox__label" for="wa-param-{{ $field['index'] }}">
                                            {{ $field['label'] }}
                                            <span class="crm-wa-inbox__required" aria-hidden="true">*</span>
                                        </label>
                                        <input
                                            id="wa-param-{{ $field['index'] }}"
                                            type="text"
                                            wire:model.live="sendWhatsAppTemplateParams.{{ $field['index'] }}"
                                            placeholder="{{ $field['placeholder'] }}"
                                            class="crm-wa-inbox__input"
                                            required
                                        />
                                        <p class="crm-wa-inbox__field-hint">{{ $field['hint'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (filled($sendWhatsAppTemplatePreview))
                            <div class="crm-wa-inbox__preview">
                                <p class="crm-wa-inbox__preview-label">What parent will see</p>
                                <p class="crm-wa-inbox__preview-body">{{ $sendWhatsAppTemplatePreview }}</p>
                            </div>
                        @endif

                        <button
                            type="button"
                            wire:click="sendWhatsAppMessage"
                            wire:loading.attr="disabled"
                            wire:target="sendWhatsAppMessage"
                            class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--full"
                        >
                            <span wire:loading.remove wire:target="sendWhatsAppMessage">Send template</span>
                            <span wire:loading wire:target="sendWhatsAppMessage">Sending…</span>
                        </button>
                    @endif
                @endif
            </section>

            @if ($metaRoutingActive && $metaSessionOpen)
                <section class="crm-wa-inbox__composer crm-wa-inbox__composer--reply">
                    <div class="crm-wa-inbox__composer-head">
                        <h3 class="crm-wa-inbox__composer-title">Quick reply</h3>
                        <p class="crm-wa-inbox__composer-lead">Plain text — only while the 24h window is open.</p>
                    </div>
                    <div class="crm-wa-inbox__field">
                        <label class="crm-wa-inbox__label" for="wa-quick-reply">Message</label>
                        <textarea
                            id="wa-quick-reply"
                            wire:model.live="metaReplyText"
                            rows="3"
                            class="crm-wa-inbox__textarea"
                            placeholder="Type your reply… emojis work too 🙂"
                        ></textarea>
                    </div>
                    <div class="crm-wa-inbox__field">
                        <label class="crm-wa-inbox__label" for="wa-quick-attachment">Photo, video, or file</label>
                        <input
                            id="wa-quick-attachment"
                            type="file"
                            wire:model="metaReplyAttachment"
                            accept="image/*,video/*,audio/*,.pdf,.doc,.docx"
                            class="crm-wa-inbox__file"
                        />
                        <p class="crm-wa-inbox__field-hint">Images, videos, voice notes, and PDFs — like WhatsApp. Max 16 MB for video, 5 MB for images.</p>
                        <div wire:loading wire:target="metaReplyAttachment,sendMetaMedia" class="crm-wa-inbox__hint">Uploading…</div>
                    </div>
                    <div class="crm-wa-inbox__composer-actions">
                        @if ($metaReplyAttachment)
                            <button
                                type="button"
                                wire:click="sendMetaMedia"
                                wire:loading.attr="disabled"
                                wire:target="sendMetaMedia,metaReplyAttachment"
                                class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--reply crm-wa-inbox__send-btn--full"
                            >
                                <span wire:loading.remove wire:target="sendMetaMedia,metaReplyAttachment">Send attachment</span>
                                <span wire:loading wire:target="sendMetaMedia,metaReplyAttachment">Sending…</span>
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click="sendMetaReply"
                            wire:loading.attr="disabled"
                            wire:target="sendMetaReply"
                            class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--reply crm-wa-inbox__send-btn--full"
                        >
                            <span wire:loading.remove wire:target="sendMetaReply">Send reply</span>
                            <span wire:loading wire:target="sendMetaReply">Sending…</span>
                        </button>
                    </div>
                </section>
            @endif
        </aside>

        <section
            class="crm-wa-inbox__thread"
            wire:key="wa-thread-{{ count($messageThread) }}-{{ $lastThreadKey }}"
            aria-label="WhatsApp conversation"
        >
            <div class="crm-wa-inbox__thread-head">
                <h3 class="crm-wa-inbox__thread-title">Conversation</h3>
                <p class="crm-wa-inbox__thread-meta">{{ count($messageThread) }} messages</p>
            </div>

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
    </div>
</div>
