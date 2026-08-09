<div
    x-data
    class="pointer-events-none fixed bottom-[5.25rem] right-4 z-40 flex flex-col items-end gap-3 lg:bottom-6 lg:right-6"
>
    @if ($open)
        <div
            wire:key="ask-crm-panel"
            class="pointer-events-auto flex h-[min(32rem,70vh)] w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-white/10 dark:bg-gray-900"
        >
            <div class="flex items-center justify-between gap-2 border-b border-gray-100 bg-primary-600 px-3 py-2.5 text-white dark:border-white/10">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">Ask CRM</p>
                    <p class="truncate text-[11px] text-white/80">Attendance · Fees · Homework</p>
                </div>
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-lg p-1.5 hover:bg-white/15"
                    aria-label="Close chat"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 space-y-2.5 overflow-y-auto px-3 py-3">
                @foreach ($messages as $index => $item)
                    @php
                        $safe = e($item['text']);
                        $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe) ?? $safe;
                        $safe = nl2br($safe);
                    @endphp
                    <div
                        wire:key="ask-crm-w-msg-{{ $index }}"
                        @class([
                            'flex',
                            'justify-end' => $item['role'] === 'user',
                            'justify-start' => $item['role'] !== 'user',
                        ])
                    >
                        <div
                            @class([
                                'max-w-[92%] rounded-2xl px-3 py-2 text-[13px] leading-relaxed',
                                'bg-primary-600 text-white' => $item['role'] === 'user',
                                'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' => $item['role'] !== 'user',
                            ])
                        >
                            {!! $safe !!}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-gray-100 px-3 py-2.5 dark:border-white/10">
                <div class="mb-2 flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        wire:click="askExample('What is Ayyush attendance today?')"
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300"
                    >
                        Attendance
                    </button>
                    <button
                        type="button"
                        wire:click="askExample('How much fee pending for Ayyush?')"
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300"
                    >
                        Fees
                    </button>
                    <button
                        type="button"
                        wire:click="askExample('Homework not done for Ayyush this week?')"
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300"
                    >
                        Homework
                    </button>
                </div>

                <form wire:submit="send" class="flex items-end gap-2">
                    <textarea
                        wire:model="message"
                        rows="2"
                        placeholder="Ask about a student…"
                        class="min-h-[2.5rem] flex-1 resize-none rounded-xl border-gray-200 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    ></textarea>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="send,askExample"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white hover:bg-primary-500 disabled:opacity-50"
                        aria-label="Send"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    @endif

    <button
        type="button"
        wire:click="toggle"
        class="pointer-events-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg ring-4 ring-primary-600/20 transition hover:bg-primary-500 hover:scale-105"
        aria-label="{{ $open ? 'Close Ask CRM' : 'Open Ask CRM' }}"
    >
        @if ($open)
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        @else
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
            </svg>
        @endif
    </button>
</div>
