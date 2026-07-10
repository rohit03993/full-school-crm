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
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">How was this visit resolved?</p>
                        <div class="mt-3 space-y-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="radio" wire:model.live="closeMeetingResolutionMode" value="resolved" class="border-gray-300">
                                Resolved on spot (no case)
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="radio" wire:model.live="closeMeetingResolutionMode" value="open_case" class="border-gray-300">
                                Open a case &amp; assign for follow-up
                            </label>
                        </div>
                    </div>

                    @if (($closeMeetingResolutionMode ?? 'resolved') === 'resolved')
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Meeting outcome</label>
                            <x-crm.select wire:model="closeMeetingCampusOutcome" class="mt-2" required>
                                @foreach ($campusOutcomeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-crm.select>
                        </div>
                    @else
                        <div class="rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-500/20 dark:bg-primary-500/5">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">Case details</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">The visit is recorded and a traceable case is opened for the assigned staff member.</p>

                            <div class="mt-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Case type</label>
                                <x-crm.select wire:model="closeMeetingCaseType" class="mt-2" required>
                                    @foreach ($caseTypeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-crm.select>
                            </div>
                            <div class="mt-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Case title</label>
                                <input
                                    type="text"
                                    wire:model="closeMeetingCaseTitle"
                                    class="fi-crm-input mt-2 block w-full"
                                    placeholder="Short summary (optional — defaults from meeting notes)"
                                >
                            </div>
                            <div class="mt-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Assign to <span class="text-danger-600">*</span></label>
                                <x-crm.select wire:model="closeMeetingCaseAssigneeId" class="mt-2" required>
                                    <option value="">Select staff…</option>
                                    @foreach ($callingStaffOptions as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-crm.select>
                            </div>
                            <div class="mt-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Handoff note <span class="text-danger-600">*</span></label>
                                <textarea
                                    wire:model="closeMeetingCaseHandoffNote"
                                    rows="2"
                                    required
                                    class="fi-crm-input mt-2 block w-full"
                                    placeholder="What should the assignee do next?"
                                ></textarea>
                            </div>
                        </div>
                    @endif
                @else
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Lead status</label>
                        <x-crm.select wire:model.live="closeMeetingStatus" class="mt-2" required>
                            @foreach ($visitStatusOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-crm.select>
                    </div>

                    @if (! in_array($closeMeetingStatus ?? '', ['not_interested', 'joined'], true))
                        <div class="rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-500/20 dark:bg-primary-500/5">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">Next step — calling</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">Assign follow-up calls after this meeting. The lead appears in the telecaller queue on the follow-up date.</p>

                            <div class="mt-3 space-y-2">
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="radio" wire:model.live="closeMeetingCallingMode" value="self" class="border-gray-300">
                                    I will call this lead
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="radio" wire:model.live="closeMeetingCallingMode" value="staff" class="border-gray-300">
                                    Assign to telecaller / staff
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="radio" wire:model.live="closeMeetingCallingMode" value="none" class="border-gray-300">
                                    Do not assign for calling yet
                                </label>
                            </div>

                            @if (($closeMeetingCallingMode ?? 'self') === 'staff')
                                <div class="mt-3">
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Assign to</label>
                                    <x-crm.select wire:model="closeMeetingCallingStaffId" class="mt-2" required>
                                        <option value="">Select staff…</option>
                                        @foreach ($callingStaffOptions as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </x-crm.select>
                                </div>
                                <div class="mt-3">
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Handoff note for telecaller <span class="text-danger-600">*</span></label>
                                    <textarea
                                        wire:model="closeMeetingCallingHandoffNote"
                                        rows="2"
                                        required
                                        class="fi-crm-input mt-2 block w-full"
                                        placeholder="What should the telecaller discuss on the call?"
                                    ></textarea>
                                </div>
                            @endif
                        </div>
                    @endif
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
