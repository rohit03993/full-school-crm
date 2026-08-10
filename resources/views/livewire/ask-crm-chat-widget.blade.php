<div
    style="position:fixed;right:1.25rem;bottom:1.5rem;z-index:9999;display:flex;flex-direction:column;align-items:flex-end;gap:0.75rem;pointer-events:none;"
>
    @if ($open)
        <div
            wire:key="ask-crm-panel"
            style="pointer-events:auto;display:flex;flex-direction:column;width:min(22rem,calc(100vw - 2rem));height:min(32rem,70vh);overflow:hidden;border-radius:1rem;border:1px solid #e5e7eb;background:#fff;box-shadow:0 25px 50px rgba(0,0,0,.25);"
        >
            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;padding:0.65rem 0.85rem;background:#f59e0b;color:#fff;">
                <div style="min-width:0;">
                    <p style="margin:0;font-size:0.875rem;font-weight:700;">Ask CRM</p>
                    <p style="margin:0;font-size:0.7rem;opacity:.9;">
                        @if ($lastStudentName)
                            Talking about: {{ $lastStudentName }}
                        @else
                            Full student profile · ask naturally
                        @endif
                    </p>
                </div>
                <div style="display:flex;align-items:center;gap:0.25rem;flex-shrink:0;">
                    @if (count($messages) > 1)
                        <button
                            type="button"
                            wire:click="clearChat"
                            wire:loading.attr="disabled"
                            wire:target="clearChat"
                            style="border:1px solid rgba(255,255,255,.45);background:rgba(255,255,255,.12);color:#fff;padding:0.25rem 0.45rem;border-radius:999px;font-size:0.62rem;font-weight:700;cursor:pointer;"
                            title="Clear chat history and start fresh"
                        >
                            Clear chat
                        </button>
                    @endif
                    @if ($lastStudentName)
                        <button
                            type="button"
                            wire:click="clearStudentContext"
                            wire:loading.attr="disabled"
                            wire:target="clearStudentContext"
                            style="border:1px solid rgba(255,255,255,.45);background:rgba(255,255,255,.12);color:#fff;padding:0.25rem 0.45rem;border-radius:999px;font-size:0.62rem;font-weight:700;cursor:pointer;"
                            title="Clear student context and ask about someone else"
                        >
                            New student
                        </button>
                    @endif
                    <button
                        type="button"
                        wire:click="close"
                        style="border:0;background:transparent;color:#fff;padding:0.35rem;border-radius:0.5rem;cursor:pointer;"
                        aria-label="End chat and close session"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <div
                id="ask-crm-messages"
                x-data
                x-on:ask-crm-scroll-bottom.window="$el.scrollTop = $el.scrollHeight"
                style="flex:1;overflow-y:auto;padding:0.75rem;display:flex;flex-direction:column;gap:0.6rem;background:#f9fafb;"
            >
                @foreach ($messages as $index => $item)
                    @php
                        $isUser = $item['role'] === 'user';
                        $chunks = preg_split('/(\[[^\]]+\]\([^)]+\))/', (string) ($item['text'] ?? ''), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
                    @endphp
                    <div
                        wire:key="ask-crm-w-msg-{{ $index }}"
                        style="display:flex;justify-content:{{ $isUser ? 'flex-end' : 'flex-start' }};"
                    >
                        <div style="max-width:92%;border-radius:1rem;padding:0.55rem 0.75rem;font-size:0.8125rem;line-height:1.45;white-space:normal;background:{{ $isUser ? '#f59e0b' : '#fff' }};color:{{ $isUser ? '#fff' : '#111827' }};border:{{ $isUser ? '0' : '1px solid #e5e7eb' }};">
                            @foreach ($chunks as $chunk)
                                @if (preg_match('/^\[(.+)\]\((.+)\)$/', $chunk, $link))
                                    <a href="{{ $link[2] }}" target="_blank" rel="noopener noreferrer" style="color:{{ $isUser ? '#fff' : '#b45309' }};font-weight:700;text-decoration:underline;">{{ $link[1] }}</a>
                                @else
                                    @php
                                        $part = e($chunk);
                                        $part = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $part) ?? $part;
                                        $part = nl2br($part);
                                    @endphp
                                    {!! $part !!}
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($isSending)
                    <div wire:key="ask-crm-typing" style="display:flex;justify-content:flex-start;">
                        <div style="border-radius:1rem;padding:0.55rem 0.75rem;font-size:0.8125rem;background:#fff;border:1px solid #e5e7eb;color:#6b7280;">
                            Reading CRM data…
                        </div>
                    </div>
                @endif
            </div>

            <div style="border-top:1px solid #e5e7eb;padding:0.65rem 0.75rem;background:#fff;">
                <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-bottom:0.5rem;">
                    @if ($canAskAttendance)
                        <button type="button" wire:click="askExample('What is Ayyush attendance today?')" wire:loading.attr="disabled" wire:target="send,askExample" style="border:0;border-radius:999px;background:#f3f4f6;padding:0.2rem 0.55rem;font-size:0.65rem;font-weight:700;cursor:pointer;">Attendance</button>
                    @endif
                    @if ($canAskFees)
                        <button type="button" wire:click="askExample('How much fee pending for Ayyush?')" wire:loading.attr="disabled" wire:target="send,askExample" style="border:0;border-radius:999px;background:#f3f4f6;padding:0.2rem 0.55rem;font-size:0.65rem;font-weight:700;cursor:pointer;">Fees</button>
                    @endif
                    @if ($canAskHomework)
                        <button type="button" wire:click="askExample('ABHINAV SINGH - homework for 9 aug 2026')" wire:loading.attr="disabled" wire:target="send,askExample" style="border:0;border-radius:999px;background:#f3f4f6;padding:0.2rem 0.55rem;font-size:0.65rem;font-weight:700;cursor:pointer;">Homework</button>
                        <button type="button" wire:click="askExample('ABHINAV SINGH homework status — whatsapp message for parent')" wire:loading.attr="disabled" wire:target="send,askExample" style="border:0;border-radius:999px;background:#f3f4f6;padding:0.2rem 0.55rem;font-size:0.65rem;font-weight:700;cursor:pointer;">Parent msg</button>
                    @endif
                </div>

                <form wire:submit="send" style="display:flex;align-items:flex-end;gap:0.5rem;">
                    <textarea
                        wire:model="message"
                        rows="2"
                        placeholder="Ask about a student…"
                        wire:loading.attr="disabled"
                        wire:target="send,askExample"
                        style="flex:1;min-height:2.5rem;resize:none;border-radius:0.75rem;border:1px solid #d1d5db;padding:0.5rem 0.65rem;font-size:0.875rem;"
                    ></textarea>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="send,askExample"
                        style="height:2.5rem;width:2.5rem;border:0;border-radius:0.75rem;background:#f59e0b;color:#fff;font-weight:700;cursor:pointer;opacity:{{ $isSending ? '0.6' : '1' }};"
                        aria-label="Send"
                    >
                        →
                    </button>
                </form>
            </div>
        </div>
    @endif

    <button
        type="button"
        wire:click="toggle"
        style="pointer-events:auto;display:inline-flex;align-items:center;gap:0.5rem;border:0;border-radius:999px;background:#f59e0b;color:#fff;padding:0.75rem 1.1rem;font-size:0.875rem;font-weight:800;box-shadow:0 10px 25px rgba(245,158,11,.45);cursor:pointer;"
        aria-label="{{ $open ? 'End Ask CRM session' : ($hasActiveSession ? 'Resume Ask CRM chat' : 'Open Ask CRM') }}"
    >
        <span aria-hidden="true">💬</span>
        <span>{{ $open ? 'End chat' : ($hasActiveSession ? 'Resume chat' : 'Ask CRM') }}</span>
    </button>
</div>
