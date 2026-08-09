<x-filament-panels::page>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Conversation</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Answers come from live CRM data for your institute.</p>
            </div>

            <div class="max-h-[28rem] space-y-3 overflow-y-auto px-4 py-4">
                @foreach ($messages as $index => $item)
                    @php
                        $safe = e($item['text']);
                        $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe) ?? $safe;
                        $safe = nl2br($safe);
                    @endphp
                    <div
                        wire:key="ask-crm-msg-{{ $index }}"
                        @class([
                            'flex',
                            'justify-end' => $item['role'] === 'user',
                            'justify-start' => $item['role'] !== 'user',
                        ])
                    >
                        <div
                            @class([
                                'max-w-[90%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed',
                                'bg-primary-600 text-white' => $item['role'] === 'user',
                                'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' => $item['role'] !== 'user',
                            ])
                        >
                            {!! $safe !!}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-gray-100 px-4 py-3 dark:border-white/10">
                <div class="mb-2 flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="askExample('What is Ayyush attendance today?')"
                        class="rounded-full border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        Attendance today
                    </button>
                    <button
                        type="button"
                        wire:click="askExample('What is Ayyush attendance this month?')"
                        class="rounded-full border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        Attendance %
                    </button>
                    <button
                        type="button"
                        wire:click="askExample('How much fee pending for Ayyush?')"
                        class="rounded-full border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        Fee pending
                    </button>
                    <button
                        type="button"
                        wire:click="askExample('Homework not done for Ayyush this week?')"
                        class="rounded-full border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        Homework week
                    </button>
                </div>

                <form wire:submit="send" class="flex items-end gap-2">
                    <div class="flex-1">
                        <label for="ask-crm-message" class="sr-only">Message</label>
                        <textarea
                            id="ask-crm-message"
                            wire:model="message"
                            rows="2"
                            placeholder="Ask about a student…"
                            class="w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        ></textarea>
                    </div>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="send,askExample"
                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="send,askExample">Ask</span>
                        <span wire:loading wire:target="send,askExample">…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
