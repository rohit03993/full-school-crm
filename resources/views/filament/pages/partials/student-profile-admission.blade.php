<div>
    @if (! $admissionTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading admission details…</p>
    @elseif (! $activeAdmission)
        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-white/20 sm:px-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">No admission started yet.</p>
            <p class="mt-2 text-xs text-gray-500">Use <strong>Convert to Admission</strong> when the student is ready.</p>
        </div>
    @else
        @if ($activeAdmission->canBeApproved() && ! auth()->user()?->can('approve', $activeAdmission))
            <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-500/30 dark:bg-sky-500/10">
                <p class="text-sm font-medium text-sky-900 dark:text-sky-100">Waiting for approval</p>
                <p class="mt-1 text-sm text-sky-800 dark:text-sky-200">
                    The admission form is submitted. An authorized admission officer or admin must verify documents and approve to create the roll number.
                </p>
            </div>
        @elseif ($activeAdmission->status->value === 'submitted' && ! $activeAdmission->hasReviewableSubmission())
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                <p class="text-sm font-medium text-amber-900 dark:text-amber-100">Waiting for admission form</p>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                    The student must submit academic details and documents via the student portal or staff can complete the form here.
                </p>
            </div>
        @endif

        @if ($activeAdmission->isEditable())
            @include('filament.partials.admission-form-edit', [
                'activeAdmission' => $activeAdmission,
                'record' => $record,
            ])
        @elseif ($activeAdmission->hasReviewableSubmission())
            @include('filament.partials.admission-form-review', [
                'admission' => $activeAdmission,
                'student' => $record,
            ])
        @endif

        @if ($activeAdmission->canBeApproved() && auth()->user()?->can('approve', $activeAdmission))
            <div class="mt-4 flex flex-col gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                <p class="text-sm text-warning-900 dark:text-warning-200">
                    Verify the admission form above, then approve or return for correction.
                </p>
                <x-filament::button wire:click="approveAdmission" color="success" class="w-full sm:w-auto">
                    Approve Admission
                </x-filament::button>

                <x-crm.text-input
                    label="Return remarks"
                    model="returnRemarks"
                    placeholder="Reason for returning form"
                    class="flex-1"
                />

                <x-filament::button wire:click="returnAdmission" color="warning" class="w-full sm:w-auto">
                    Return for Correction
                </x-filament::button>
            </div>
        @elseif (! $activeAdmission->isEditable() && ! $activeAdmission->hasReviewableSubmission())
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-white/20">
                <p class="text-sm text-gray-600 dark:text-gray-400">Admission form not submitted yet.</p>
                <p class="mt-2 text-xs text-gray-500">Fill academic details and upload documents to continue.</p>
            </div>
        @endif
    @endif
</div>
