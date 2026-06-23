@php
    use App\Enums\CallDirection;
    use App\Enums\CallQuickTag;
    use App\Enums\CallStatus;
    use App\Enums\VisitStatus;
    use App\Enums\WhoAnswered;
@endphp

@if ($showLogCallModal ?? false)
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" wire:click.self="closeLogCallModal">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white shadow-xl dark:bg-gray-900 sm:rounded-2xl">
            <div class="sticky top-0 border-b border-gray-100 bg-white px-4 py-4 dark:border-white/10 dark:bg-gray-900 sm:px-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-950 dark:text-white">Log call result</h3>
                        @if (filled($logCallLeadName ?? null))
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                {{ $logCallLeadName }}
                                @if (filled($logCallLeadPhone ?? null))
                                    · {{ $logCallLeadPhone }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <button type="button" wire:click="closeLogCallModal" class="shrink-0 text-sm font-semibold text-gray-500">Close</button>
                </div>
            </div>

            <form wire:submit="submitLogCall" class="fi-crm-form space-y-4 px-4 py-4 sm:px-6 sm:py-5">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Direction</label>
                    <x-crm.select wire:model="logCallForm.call_direction" class="mt-2">
                        @foreach (CallDirection::cases() as $direction)
                            <option value="{{ $direction->value }}">{{ $direction->label() }}</option>
                        @endforeach
                    </x-crm.select>
                </div>

                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live.boolean="logCallForm.call_connected" class="rounded border-gray-300">
                    Call connected
                </label>

                @if (! ($logCallForm['call_connected'] ?? true))
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
                        <x-crm.select wire:model="logCallForm.call_status" class="mt-2" required>
                            <option value="">Select…</option>
                            @foreach (CallStatus::notConnectedOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-crm.select>
                    </div>
                @else
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Who answered?</label>
                        <x-crm.select wire:model="logCallForm.who_answered" class="mt-2" required>
                            <option value="">Select…</option>
                            @foreach (WhoAnswered::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-crm.select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Lead status after call</label>
                        <x-crm.select wire:model.live="logCallForm.visit_status" class="mt-2" required>
                            <option value="">Select…</option>
                            @foreach (VisitStatus::cases() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </x-crm.select>
                    </div>
                @endif

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Duration (minutes)</label>
                    <input type="number" min="0" max="600" wire:model="logCallForm.duration_minutes" class="fi-crm-input mt-2">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Call notes</label>
                    <textarea wire:model="logCallForm.call_notes" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800" @if ($logCallForm['call_connected'] ?? true) required minlength="10" @endif></textarea>
                    @if ($logCallForm['call_connected'] ?? true)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">At least 10 characters required.</p>
                    @endif
                </div>

                @if ($logCallForm['call_connected'] ?? true)
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Quick tags</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach (CallQuickTag::options() as $value => $label)
                                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" wire:model="logCallForm.tags" value="{{ $value }}" class="rounded border-gray-300">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                @php
                    $requiresFollowUp = ($logCallForm['call_connected'] ?? true)
                        && in_array($logCallForm['visit_status'] ?? '', ['interested', 'follow_up_required', 'admission_ready'], true);
                @endphp
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Next follow-up @if ($requiresFollowUp) <span class="text-danger-600">*</span> @endif
                    </label>
                    <input type="datetime-local" wire:model="logCallForm.next_followup_at" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800" @if ($requiresFollowUp) required @endif>
                    @if ($requiresFollowUp)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Required when lead status is Interested, Follow-up Required, or Admission Ready.</p>
                    @endif
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" wire:click="closeLogCallModal" class="flex-1 rounded-xl border border-gray-300 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-white/10 dark:text-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white hover:bg-primary-500">
                        @if (($logCallModalMode ?? 'queue') === 'queue')
                            Save & next lead
                        @else
                            Save call log
                        @endif
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
