<div wire:init="loadMessagesTab" class="crm-wa-inbox">
    @php
        $lastThreadKey = $messageThread === []
            ? 'empty'
            : ($messageThread[array_key_last($messageThread)]['key'] ?? 'empty');
    @endphp

    <header class="crm-wa-inbox__toolbar">
        <div class="crm-wa-inbox__toolbar-main">
            <div class="crm-wa-inbox__toolbar-copy">
                <p class="crm-wa-inbox__title">WhatsApp with parent</p>
                <p class="crm-wa-inbox__subtitle">
                    <span class="crm-wa-inbox__phone">{{ $record->mobile ?? 'No mobile on file' }}</span>
                    <span class="crm-wa-inbox__dot" aria-hidden="true">·</span>
                    <span>Provider: <strong>{{ $whatsappProviderLabel }}</strong></span>
                </p>
            </div>
            @if ($metaRoutingActive)
                <span @class([
                    'crm-wa-pill',
                    'crm-wa-pill--open' => $metaSessionOpen,
                    'crm-wa-pill--closed' => ! $metaSessionOpen,
                ])>
                    {{ $metaSessionOpen ? '24h reply window open' : 'Reply window closed' }}
                </span>
            @endif
        </div>
        @if ($metaRoutingActive && ! $metaSessionOpen)
            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--banner">
                Parents must message you first (or within 24 hours of their last message) before you can send a free-text reply.
            </p>
        @endif
        @if (filled($waTemplateSyncHint))
            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--banner">{{ $waTemplateSyncHint }}</p>
        @endif
    </header>

    <div class="crm-wa-inbox__layout">
        <aside class="crm-wa-inbox__compose">
            <section class="crm-wa-inbox__composer">
                <div class="crm-wa-inbox__composer-head">
                    <h3 class="crm-wa-inbox__composer-title">Send template</h3>
                    <p class="crm-wa-inbox__composer-lead">Pick a template, fill only the fields it needs, then send.</p>
                </div>

                @if (blank($record->mobile))
                    <p class="crm-wa-inbox__hint crm-wa-inbox__hint--danger">Add a mobile number on the student profile before sending WhatsApp.</p>
                @elseif ($waTemplates->isEmpty())
                    <p class="crm-wa-inbox__hint">
                        No templates synced.
                        @if ($metaRoutingActive)
                            Open <strong>{{ \App\Support\CrmNavigation::whatsAppMenu('Connection & Setup') }}</strong> and click Sync templates.
                        @else
                            Open <strong>{{ \App\Support\CrmNavigation::whatsAppMenu('Automations') }}</strong> and sync templates.
                        @endif
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
                                        ({{ (int) $template->param_count }} {{ (int) $template->param_count === 1 ? 'field' : 'fields' }})
                                    @else
                                        (no variables)
                                    @endif
                                </option>
                            @endforeach
                        </x-crm.select>
                    </div>

                    @if ($sendWhatsAppTemplateId)
                        @if ($sendWhatsAppTemplateParamCount === 0)
                            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--info">
                                This template has no variables — you can send it as-is.
                            </p>
                        @else
                            <div class="crm-wa-inbox__param-grid">
                                @foreach ($sendWhatsAppTemplateFields as $field)
                                    <div class="crm-wa-inbox__field" wire:key="wa-param-{{ $field['index'] }}">
                                        <label class="crm-wa-inbox__label" for="wa-param-{{ $field['index'] }}">
                                            {{ $field['label'] }}
                                        </label>
                                        <input
                                            id="wa-param-{{ $field['index'] }}"
                                            type="text"
                                            wire:model.live="sendWhatsAppTemplateParams.{{ $field['index'] }}"
                                            placeholder="{{ $field['placeholder'] }}"
                                            class="crm-wa-inbox__input"
                                        />
                                        <p class="crm-wa-inbox__field-hint">{{ $field['hint'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (filled($sendWhatsAppTemplatePreview))
                            <div class="crm-wa-inbox__preview">
                                <p class="crm-wa-inbox__preview-label">Message preview</p>
                                <p class="crm-wa-inbox__preview-body">{{ $sendWhatsAppTemplatePreview }}</p>
                            </div>
                        @elseif (filled($sendWhatsAppSelectedTemplateName))
                            <div class="crm-wa-inbox__preview crm-wa-inbox__preview--muted">
                                <p class="crm-wa-inbox__preview-label">Template</p>
                                <p class="crm-wa-inbox__preview-body">{{ $sendWhatsAppSelectedTemplateName }}</p>
                            </div>
                        @endif

                        <button
                            type="button"
                            wire:click="sendWhatsAppMessage"
                            wire:loading.attr="disabled"
                            wire:target="sendWhatsAppMessage"
                            class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--full"
                        >
                            <span wire:loading.remove wire:target="sendWhatsAppMessage">Send to {{ $record->mobile }}</span>
                            <span wire:loading wire:target="sendWhatsAppMessage">Sending…</span>
                        </button>
                    @endif
                @endif
            </section>

            @if ($metaRoutingActive)
                <section class="crm-wa-inbox__composer crm-wa-inbox__composer--reply">
                    <h3 class="crm-wa-inbox__composer-title">Quick reply</h3>
                    <p class="crm-wa-inbox__hint">
                        Free-text only when the parent messaged in the last 24 hours.
                    </p>
                    <div class="crm-wa-inbox__field">
                        <label class="crm-wa-inbox__label">Your message</label>
                        <textarea
                            wire:model="metaReplyText"
                            rows="4"
                            class="crm-wa-inbox__textarea"
                            placeholder="{{ $metaSessionOpen ? 'Type a reply…' : 'Reply window closed — waiting for parent message' }}"
                            @disabled(! $metaSessionOpen)
                        ></textarea>
                    </div>
                    <button
                        type="button"
                        wire:click="sendMetaReply"
                        wire:loading.attr="disabled"
                        wire:target="sendMetaReply"
                        class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--secondary crm-wa-inbox__send-btn--full"
                        @disabled(! $metaSessionOpen || blank($metaReplyText))
                    >
                        <span wire:loading.remove wire:target="sendMetaReply">Send reply</span>
                        <span wire:loading wire:target="sendMetaReply">Sending…</span>
                    </button>
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
                <p class="crm-wa-inbox__thread-meta">{{ count($messageThread) }} message{{ count($messageThread) === 1 ? '' : 's' }}</p>
            </div>

            @if (! $messagesTabLoaded)
                <p class="crm-wa-inbox__hint crm-wa-inbox__hint--center">Loading conversation…</p>
            @elseif ($messageThread === [])
                <div class="crm-wa-inbox__empty">
                    <p class="font-medium text-gray-700 dark:text-gray-200">No WhatsApp messages yet</p>
                    <p class="crm-wa-inbox__hint">Template sends and parent replies will show here in chat order.</p>
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
                        ])>
                            <div class="crm-wa-bubble__meta">
                                <span>{{ ($message['direction'] ?? '') === 'inbound' ? 'Parent' : 'School' }}</span>
                                <span>{{ $message['at_label'] ?? '' }}</span>
                            </div>
                            @if (! empty($message['templateName']))
                                <p class="crm-wa-bubble__template">{{ $message['templateName'] }}</p>
                            @endif
                            <p class="crm-wa-bubble__body">{{ $message['body'] ?? '' }}</p>
                            <div class="crm-wa-bubble__footer">
                                <span @class([
                                    'crm-wa-status font-medium',
                                    'crm-wa-status--ok' => in_array($message['status'] ?? '', ['sent', 'delivered', 'read', 'received'], true),
                                    'crm-wa-status--warn' => in_array($message['status'] ?? '', ['pending', 'processing', 'queued'], true),
                                    'crm-wa-status--bad' => ($message['status'] ?? '') === 'failed',
                                ])>
                                    {{ $message['statusLabel'] ?? '' }}
                                </span>
                                @if (! empty($message['provider']))
                                    <span class="crm-wa-bubble__provider">{{ strtoupper($message['provider']) }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
