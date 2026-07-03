@if ($showCloseMeetingModal ?? false)
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" wire:click.self="cancelCloseMeetingModal">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white shadow-xl dark:bg-gray-900 sm:rounded-2xl">
            <div class="sticky top-0 border-b border-gray-100 bg-white px-4 py-4 dark:border-white/10 dark:bg-gray-900 sm:px-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-950 dark:text-white">Close the meeting</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Submit notes to close this assignment.</p>
                    </div>
                    <button type="button" wire:click="cancelCloseMeetingModal" class="shrink-0 text-sm font-semibold text-gray-500">Close</button>
                </div>
            </div>

            <form wire:submit="submitCloseMeeting" class="fi-crm-form space-y-4 px-4 py-4 sm:px-6 sm:py-5">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Meeting notes</label>
                    <textarea
                        wire:model="closeMeetingNotes"
                        rows="4"
                        required
                        class="fi-crm-input mt-2 block w-full"
                        placeholder="What was discussed and the outcome…"
                    ></textarea>
                </div>

                @if ($isEnrolledStudent ?? false)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Meeting outcome</label>
                        <x-crm.select wire:model="closeMeetingCampusOutcome" class="mt-2" required>
                            @foreach ($campusOutcomeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-crm.select>
                    </div>
                @else
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Lead status</label>
                        <x-crm.select wire:model="closeMeetingStatus" class="mt-2" required>
                            @foreach ($visitStatusOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-crm.select>
                    </div>
                @endif

                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end dark:border-white/10">
                    <x-filament::button type="button" color="gray" wire:click="cancelCloseMeetingModal">
                        Cancel
                    </x-filament::button>
                    <x-filament::button type="submit" color="success">
                        Close the meeting
                    </x-filament::button>
                </div>
            </form>
        </div>
    </div>
@endif
