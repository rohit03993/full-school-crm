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
                <button
                    type="button"
                    wire:click="close"
                    style="border:0;background:transparent;color:#fff;padding:0.35rem;border-radius:0.5rem;cursor:pointer;"
                    aria-label="End chat and clear session"
                >
                    ✕
                </button>
            </div>

            <div style="flex:1;overflow-y:auto;padding:0.75rem;display:flex;flex-direction:column;gap:0.6rem;background:#f9fafb;">
                @foreach ($messages as $index => $item)
                    @php
                        $safe = e($item['text']);
                        $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe) ?? $safe;
                        $safe = nl2br($safe);
                        $isUser = $item['role'] === 'user';
                    @endphp
                    <div
                        wire:key="ask-crm-w-msg-{{ $index }}"
                        style="display:flex;justify-content:{{ $isUser ? 'flex-end' : 'flex-start' }};"
                    >
                        <div style="max-width:92%;border-radius:1rem;padding:0.55rem 0.75rem;font-size:0.8125rem;line-height:1.45;white-space:normal;background:{{ $isUser ? '#f59e0b' : '#fff' }};color:{{ $isUser ? '#fff' : '#111827' }};border:{{ $isUser ? '0' : '1px solid #e5e7eb' }};">
                            {!! $safe !!}
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="border-top:1px solid #e5e7eb;padding:0.65rem 0.75rem;background:#fff;">
                <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-bottom:0.5rem;">
                    <button type="button" wire:click="askExample('What is Ayyush attendance today?')" style="border:0;border-radius:999px;background:#f3f4f6;padding:0.2rem 0.55rem;font-size:0.65rem;font-weight:700;cursor:pointer;">Attendance</button>
                    <button type="button" wire:click="askExample('How much fee pending for Ayyush?')" style="border:0;border-radius:999px;background:#f3f4f6;padding:0.2rem 0.55rem;font-size:0.65rem;font-weight:700;cursor:pointer;">Fees</button>
                    <button type="button" wire:click="askExample('Homework not done for Ayyush this week?')" style="border:0;border-radius:999px;background:#f3f4f6;padding:0.2rem 0.55rem;font-size:0.65rem;font-weight:700;cursor:pointer;">Homework</button>
                </div>

                <form wire:submit="send" style="display:flex;align-items:flex-end;gap:0.5rem;">
                    <textarea
                        wire:model="message"
                        rows="2"
                        placeholder="Ask about a student…"
                        style="flex:1;min-height:2.5rem;resize:none;border-radius:0.75rem;border:1px solid #d1d5db;padding:0.5rem 0.65rem;font-size:0.875rem;"
                    ></textarea>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="send,askExample"
                        style="height:2.5rem;width:2.5rem;border:0;border-radius:0.75rem;background:#f59e0b;color:#fff;font-weight:700;cursor:pointer;"
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
