<div wire:init="loadMessagesTab" class="crm-wa-inbox">
    @php
        $templates = \App\Models\WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    @endphp

    <div class="crm-wa-inbox__toolbar">
        <div class="crm-wa-inbox__toolbar-main">
            <div class="crm-wa-inbox__toolbar-copy">
                <p class="crm-wa-inbox__title">WhatsApp with parent</p>
                <p class="crm-wa-inbox__subtitle">
                    {{ $record->mobile ?? 'No mobile on file' }}
                    · Provider: <strong>{{ $whatsappProviderLabel }}</strong>
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
    </div>

    <div class="crm-wa-inbox__composers">
        <div class="crm-wa-inbox__composer">
            <h3 class="crm-wa-inbox__composer-title">Send template</h3>
            @if (blank($record->mobile))
                <p class="crm-wa-inbox__hint crm-wa-inbox__hint--danger">Add a mobile number before sending WhatsApp.</p>
            @elseif ($templates->isEmpty())
                <p class="crm-wa-inbox__hint">
                    No templates synced.
                    @if ($metaRoutingActive)
                        Open <strong>{{ \App\Support\CrmNavigation::whatsAppMenu('Connection & Setup') }}</strong> and click Sync templates.
                    @else
                        Open <strong>{{ \App\Support\CrmNavigation::whatsAppMenu('Automations') }}</strong> and sync templates.
                    @endif
                </p>
            @else
                <div class="crm-wa-inbox__composer-row">
                    <div class="crm-wa-inbox__field">
                        <label class="crm-wa-inbox__label">Template</label>
                        <x-crm.select wire:model="sendWhatsAppTemplateId" class="mt-1">
                            <option value="">Choose template…</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </x-crm.select>
                    </div>
                    <button
                        type="button"
                        wire:click="sendWhatsAppMessage"
                        wire:loading.attr="disabled"
                        wire:target="sendWhatsAppMessage"
                        class="crm-wa-inbox__send-btn"
                    >
                        <span wire:loading.remove wire:target="sendWhatsAppMessage">Send template</span>
                        <span wire:loading wire:target="sendWhatsAppMessage">Sending…</span>
                    </button>
                </div>
            @endif
        </div>

        @if ($metaRoutingActive)
            <div class="crm-wa-inbox__composer crm-wa-inbox__composer--reply">
                <h3 class="crm-wa-inbox__composer-title">Quick reply (Meta)</h3>
                <p class="crm-wa-inbox__hint">
                    Free-text replies work only when the parent messaged you in the last 24 hours.
                </p>
                <div class="crm-wa-inbox__composer-row">
                    <div class="crm-wa-inbox__field">
                        <label class="crm-wa-inbox__label">Your message</label>
                        <textarea
                            wire:model="metaReplyText"
                            rows="3"
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
                        class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--secondary"
                        @disabled(! $metaSessionOpen || blank($metaReplyText))
                    >
                        <span wire:loading.remove wire:target="sendMetaReply">Send reply</span>
                        <span wire:loading wire:target="sendMetaReply">Sending…</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <div
        class="crm-wa-inbox__thread"
        wire:key="wa-thread-{{ $messageThread->count() }}-{{ $messageThread->last()?->key ?? 'empty' }}"
    >
        @if (! $messagesTabLoaded)
            <p class="crm-wa-inbox__hint">Loading conversation…</p>
        @elseif ($messageThread->isEmpty())
            <div class="crm-wa-inbox__empty">
                <p class="font-medium text-gray-700 dark:text-gray-200">No WhatsApp messages yet</p>
                <p class="crm-wa-inbox__hint">Template sends and parent replies will appear here.</p>
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
                        'crm-wa-bubble--in' => $message->isInbound(),
                        'crm-wa-bubble--out' => $message->isOutbound(),
                    ])>
                        <div class="crm-wa-bubble__meta">
                            <span>{{ $message->isInbound() ? 'Parent' : 'School' }}</span>
                            <span>{{ $message->at?->format('d M, h:i A') }}</span>
                        </div>
                        @if ($message->templateName)
                            <p class="crm-wa-bubble__template">{{ $message->templateName }}</p>
                        @endif
                        <p class="crm-wa-bubble__body">{{ $message->body }}</p>
                        <div class="crm-wa-bubble__footer">
                            <span @class([
                                'crm-wa-status font-medium',
                                'crm-wa-status--ok' => in_array($message->status, ['sent', 'delivered', 'read', 'received'], true),
                                'crm-wa-status--warn' => in_array($message->status, ['pending', 'processing', 'queued'], true),
                                'crm-wa-status--bad' => $message->status === 'failed',
                            ])>
                                {{ $message->statusLabel }}
                            </span>
                            @if ($message->provider)
                                <span class="crm-wa-bubble__provider">{{ strtoupper($message->provider) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
