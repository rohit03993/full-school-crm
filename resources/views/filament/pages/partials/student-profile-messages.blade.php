<div wire:init="loadMessagesTab">
    @php
        $templates = \App\Models\WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    @endphp

    <div class="mb-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Send WhatsApp</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Uses Pal Digital approved templates. Student mobile: {{ $record->mobile ?? '—' }}
        </p>

        @if (blank($record->mobile))
            <p class="mt-3 text-sm text-danger-600 dark:text-danger-400">Add a mobile number before sending WhatsApp.</p>
        @elseif ($templates->isEmpty())
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No active templates — add one under Settings → WhatsApp Settings.</p>
        @else
            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Template</label>
                    <x-crm.select wire:model="sendWhatsAppTemplateId" class="mt-2">
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
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60"
                >
                    Send WhatsApp
                </button>
            </div>
        @endif
    </div>

    @if (! $messagesTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading messages…</p>
    @elseif ($whatsappMessages->isEmpty())
        <div class="fi-section rounded-xl px-4 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">No WhatsApp messages logged yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($whatsappMessages as $message)
                <div class="rounded-xl border border-gray-100 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:px-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-bold text-gray-950 dark:text-white">
                                {{ $message->campaign?->template?->name ?? 'WhatsApp' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $message->created_at?->format('d M Y, h:i A') }}
                                · {{ $message->phone }}
                            </p>
                        </div>
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $message->status?->value === 'sent',
                            'bg-danger-100 text-danger-800 dark:bg-danger-500/15 dark:text-danger-300' => $message->status?->value === 'failed',
                            'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' => in_array($message->status?->value, ['pending', 'processing'], true),
                        ])>
                            {{ $message->status?->label() ?? 'Unknown' }}
                        </span>
                    </div>

                    @if ($message->message_sent)
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $message->message_sent }}</p>
                    @endif

                    @if ($message->error_message)
                        <p class="mt-2 text-xs text-danger-600 dark:text-danger-400">{{ $message->error_message }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
