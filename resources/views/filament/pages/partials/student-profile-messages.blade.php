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
                    <span>Meta Cloud API</span>
                </p>
            </div>
            @if ($metaRoutingActive)
                <span @class([
                    'crm-wa-pill',
                    'crm-wa-pill--open' => $metaSessionOpen,
                    'crm-wa-pill--closed' => ! $metaSessionOpen,
                ])>
                    {{ $metaSessionOpen ? '24h window open — free reply allowed' : 'Templates only' }}
                </span>
            @endif
        </div>
        @if ($metaRoutingActive && $metaSessionOpen)
            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--success">
                Parent messaged recently. Use <strong>Quick reply</strong> below for a normal text message, or send an approved template anytime.
            </p>
        @elseif ($metaRoutingActive && ! $metaSessionOpen)
            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--banner">
                Outside the 24-hour window — use an approved <strong>template</strong> until the parent messages again.
            </p>
        @endif
        @if (filled($waTemplateSyncHint))
            <p class="crm-wa-inbox__hint crm-wa-inbox__hint--banner">{{ $waTemplateSyncHint }}</p>
        @endif
    </header>

    <div class="crm-wa-inbox__layout">
        <aside class="crm-wa-inbox__compose">
            @if ($metaRoutingActive && $metaSessionOpen)
                <section class="crm-wa-inbox__composer crm-wa-inbox__composer--reply crm-wa-inbox__composer--highlight">
                    <div class="crm-wa-inbox__composer-head">
                        <h3 class="crm-wa-inbox__composer-title">Quick reply</h3>
                        <p class="crm-wa-inbox__composer-lead">Send a normal WhatsApp text — no template needed right now.</p>
                    </div>
                    <div class="crm-wa-inbox__field">
                        <label class="crm-wa-inbox__label" for="wa-quick-reply">Your message</label>
                        <textarea
                            id="wa-quick-reply"
                            wire:model.live="metaReplyText"
                            rows="3"
                            class="crm-wa-inbox__textarea"
                            placeholder="Type your reply to the parent…"
                        ></textarea>
                    </div>
                    <button
                        type="button"
                        wire:click="sendMetaReply"
                        wire:loading.attr="disabled"
                        wire:target="sendMetaReply"
                        class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--reply crm-wa-inbox__send-btn--full"
                        @disabled(! $metaSessionOpen)
                    >
                        <span wire:loading.remove wire:target="sendMetaReply">Send reply</span>
                        <span wire:loading wire:target="sendMetaReply">Sending…</span>
                    </button>
                </section>
            @endif

            <section class="crm-wa-inbox__composer">
                <div class="crm-wa-inbox__composer-head">
                    <h3 class="crm-wa-inbox__composer-title">Send template</h3>
                    <p class="crm-wa-inbox__composer-lead">Works anytime — pick a Meta-approved template and fill its fields.</p>
                </div>

                @if (blank($record->mobile))
                    <p class="crm-wa-inbox__hint crm-wa-inbox__hint--danger">Add a mobile number on the student profile before sending WhatsApp.</p>
                @elseif ($waTemplates->isEmpty())
                    <p class="crm-wa-inbox__hint">
                        No Meta templates synced yet.
                        Open <strong>{{ \App\Support\CrmNavigation::whatsAppMenu('Connection & Setup') }}</strong> and click <strong>Sync templates</strong>.
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
                                <p class="crm-wa-inbox__preview-label">Preview</p>
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
                            <span wire:loading.remove wire:target="sendWhatsAppMessage">Send template to {{ $record->mobile }}</span>
                            <span wire:loading wire:target="sendWhatsAppMessage">Sending…</span>
                        </button>
                    @endif
                @endif
            </section>
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
                    <p class="font-medium text-gray-700 dark:text-gray-200">No messages yet</p>
                    <p class="crm-wa-inbox__hint">Parent replies and your sends appear here in order.</p>
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
                            @if (! empty($message['templateName']))
                                <p class="crm-wa-bubble__template">{{ $message['templateName'] }}</p>
                            @endif
                            <p class="crm-wa-bubble__body">{{ $message['body'] ?? '' }}</p>
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
