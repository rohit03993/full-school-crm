@if ($canSendWhatsApp ?? false)
    @php
        $whatsappSendHistory = $whatsappSendHistory ?? [
            'eligible_now' => 0,
            'has_prior_class_send' => false,
            'button_label' => 'Queue WhatsApp to all students with marks',
            'sends' => [],
        ];
        $whatsappSends = $whatsappSendHistory['sends'] ?? [];
    @endphp
    <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <h3 class="text-base font-bold text-gray-950 dark:text-white">Send marks via WhatsApp</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Queues the Automations exam-marks template to every student with marks and a mobile number. Publish and PDF do not send this.
            </p>
        </div>

        <div class="grid gap-4 p-4 sm:p-6">
            @if (filled($defaultMarksTemplateName ?? null))
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Template: <strong>{{ $defaultMarksTemplateName }}</strong>
                    @if (filled($examMarksAutomationsUrl ?? null))
                        —
                        <a href="{{ $examMarksAutomationsUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Change on Automations → Exam marks</a>
                    @endif
                </p>
            @else
                <p class="text-sm text-danger-700 dark:text-danger-300">
                    No exam-marks template is set.
                    @if (filled($examMarksAutomationsUrl ?? null))
                        <a href="{{ $examMarksAutomationsUrl }}" class="font-semibold underline">Pick test_marks on Automations → Exam marks</a>
                        , or choose one below.
                    @endif
                </p>
                <x-crm.select-input label="WhatsApp template" for="{{ $whatsappTemplateInputId ?? 'wa-template' }}" wire:model="whatsappTemplateId">
                    <option value="">Select template…</option>
                    @foreach ($whatsappTemplateOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select-input>
            @endif

            @if ($whatsappSends !== [])
                <div class="rounded-xl bg-gray-50 px-3 py-3 text-sm text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Messages sent for this exam</p>
                    <p class="mt-1">
                        Eligible students with marks and mobile numbers:
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $whatsappSendHistory['eligible_now'] }}</span>
                    </p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach ($whatsappSends as $send)
                            <li>
                                <span class="font-semibold text-gray-950 dark:text-white">{{ $send['staff_name'] }}</span>
                                · {{ $send['at'] }}
                                · {{ $send['result_line'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <button
                type="button"
                wire:click="queueWhatsAppCampaign"
                wire:loading.attr="disabled"
                wire:target="queueWhatsAppCampaign"
                class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 disabled:cursor-wait disabled:opacity-70"
            >
                <span wire:loading.remove wire:target="queueWhatsAppCampaign">{{ $whatsappSendHistory['button_label'] }}</span>
                <span wire:loading wire:target="queueWhatsAppCampaign">Queuing… opening send progress</span>
            </button>
        </div>
    </div>
@endif
