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
        $canSendTemplates = (bool) $record && $waTemplates->isNotEmpty();
        $canComposeReply = ($metaRoutingActive ?? false) && ($metaSessionOpen ?? false);
        $hasAttachment = filled($metaReplyAttachment ?? null);
        $attachmentName = $hasAttachment
            ? (method_exists($metaReplyAttachment, 'getClientOriginalName')
                ? $metaReplyAttachment->getClientOriginalName()
                : 'Selected file')
            : null;
    @endphp

    @unless ($compactInbox ?? false)
    <header class="crm-wa-inbox__toolbar">
        <div class="crm-wa-inbox__toolbar-main">
            <div class="crm-wa-inbox__toolbar-copy">
                <p class="crm-wa-inbox__title">WhatsApp with parent</p>
                <p class="crm-wa-inbox__subtitle">
                    <span class="crm-wa-inbox__phone">
                        @if (\App\Support\CrmAccess::canViewStudentMobile(auth()->user()))
                            {{ $record?->mobile ?? $chatPhone ?? 'No mobile on file' }}
                        @else
                            {{ filled($record?->mobile) || filled($chatPhone ?? null) ? 'Hidden' : 'No mobile on file' }}
                        @endif
                    </span>
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
                Parent messaged recently — type a reply below, attach a file, or send a template.
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
                                <span>{{ $message['senderLabel'] ?? (($message['direction'] ?? '') === 'inbound' ? 'Parent' : 'You') }}</span>
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
            @if (blank($record?->mobile) && blank($chatPhone ?? null))
                <p class="crm-wa-inbox__hint crm-wa-inbox__hint--danger">Add a mobile number on the student profile first.</p>
            @else
                @if ($canSendTemplates)
                    <details
                        class="crm-wa-inbox__templates"
                        @if (! $canComposeReply) open @endif
                    >
                        <summary class="crm-wa-inbox__templates-summary">
                            <span class="crm-wa-inbox__templates-summary-main">
                                <x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4" />
                                <span>{{ $canComposeReply ? 'Or send a template' : 'Send template' }}</span>
                            </span>
                            <span class="crm-wa-inbox__templates-summary-hint">
                                {{ $canComposeReply ? 'Approved Meta templates' : 'Required outside 24h window' }}
                            </span>
                        </summary>

                        <div class="crm-wa-inbox__templates-body">
                            @if (filled($waTemplateSyncHint))
                                <p class="crm-wa-inbox__hint crm-wa-inbox__hint--banner">{{ $waTemplateSyncHint }}</p>
                            @endif

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
                        </div>
                    </details>
                @elseif (! $record && filled($chatPhone ?? null))
                    <p class="crm-wa-inbox__hint crm-wa-inbox__hint--banner">
                        Templates need a linked student or lead. You can still reply{{ $canComposeReply ? ' and share files' : '' }} while the 24-hour window is open.
                    </p>
                @elseif ($record && $waTemplates->isEmpty())
                    <p class="crm-wa-inbox__hint">
                        No templates synced.
                        Open <strong>{{ \App\Support\CrmNavigation::whatsAppMenu('Connection & Setup') }}</strong> → <strong>Sync templates</strong>.
                    </p>
                @endif

                @if ($canComposeReply)
                    <section class="crm-wa-inbox__composer crm-wa-inbox__composer--reply" aria-label="Quick reply">
                        @if ($hasAttachment)
                            <div class="crm-wa-inbox__attach-chip">
                                <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4 shrink-0" />
                                <span class="crm-wa-inbox__attach-chip-name" title="{{ $attachmentName }}">{{ $attachmentName }}</span>
                                <span wire:loading wire:target="metaReplyAttachment" class="crm-wa-inbox__hint crm-wa-inbox__hint--inline">Uploading…</span>
                                <button
                                    type="button"
                                    wire:click="clearMetaReplyAttachment"
                                    class="crm-wa-inbox__attach-chip-remove"
                                    title="Remove attachment"
                                >
                                    <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                                </button>
                            </div>
                        @endif

                        <div class="crm-wa-inbox__reply-bar">
                            <label
                                for="{{ $attachmentFieldId }}"
                                class="crm-wa-inbox__icon-btn"
                                title="Attach photo, video, or file"
                            >
                                <x-filament::icon icon="heroicon-o-paper-clip" class="h-5 w-5" />
                                <span class="sr-only">Attach file</span>
                                <input
                                    id="{{ $attachmentFieldId }}"
                                    type="file"
                                    wire:model="metaReplyAttachment"
                                    accept="image/*,video/*,audio/*,.pdf,.doc,.docx"
                                    class="sr-only"
                                />
                            </label>

                            <div class="crm-wa-inbox__field crm-wa-inbox__field--reply">
                                <label class="crm-wa-inbox__label sr-only" for="{{ $replyFieldId }}">Message</label>
                                <textarea
                                    id="{{ $replyFieldId }}"
                                    wire:model.live="metaReplyText"
                                    rows="1"
                                    class="crm-wa-inbox__textarea crm-wa-inbox__textarea--reply"
                                    placeholder="{{ $hasAttachment ? 'Add a caption (optional)…' : 'Type a message…' }}"
                                ></textarea>
                            </div>

                            @if ($hasAttachment)
                                <button
                                    type="button"
                                    wire:click="sendMetaMedia"
                                    wire:loading.attr="disabled"
                                    wire:target="sendMetaMedia,metaReplyAttachment"
                                    class="crm-wa-inbox__send-btn crm-wa-inbox__send-btn--reply crm-wa-inbox__send-btn--icon"
                                    title="Send attachment"
                                >
                                    <span wire:loading.remove wire:target="sendMetaMedia,metaReplyAttachment">
                                        <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5" />
                                    </span>
                                    <span wire:loading wire:target="sendMetaMedia,metaReplyAttachment">…</span>
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
            @endif
        </footer>
    </div>
</div>
