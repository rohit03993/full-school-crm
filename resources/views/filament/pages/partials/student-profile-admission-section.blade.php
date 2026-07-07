@php
    use App\Enums\AdmissionStatus;
@endphp

@if (! $admissionTabLoaded)
    <p class="text-sm text-gray-500 dark:text-gray-400">Loading admission details…</p>
@elseif (! $activeAdmission)
    <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/50 px-6 py-10 text-center dark:border-white/15 dark:bg-white/[0.02]">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
            <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-6 w-6" />
        </div>
        <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">No admission started yet</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use <strong>Convert to Admission</strong> when the student is ready to enroll.</p>
    </div>
@else
    @php
        $status = $activeAdmission->status;
        $statusClasses = match ($status) {
            AdmissionStatus::Submitted => 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30',
            AdmissionStatus::VerificationPending => 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/30',
            AdmissionStatus::Approved => 'bg-success-50 text-success-800 ring-success-200 dark:bg-success-500/10 dark:text-success-200 dark:ring-success-500/30',
            AdmissionStatus::Rejected => 'bg-danger-50 text-danger-800 ring-danger-200 dark:bg-danger-500/10 dark:text-danger-200 dark:ring-danger-500/30',
        };
    @endphp

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-4 border-b border-gray-100 bg-gradient-to-r from-primary-50/80 via-white to-white px-5 py-5 dark:border-white/10 dark:from-primary-500/10 dark:via-gray-900 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">Admission</p>
                <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $record->name }}</h3>
                <p class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                    {{ $activeAdmission->admission_number }}
                    @if ($activeAdmission->enquiry?->course?->name)
                        · {{ $activeAdmission->enquiry->course->name }}
                    @endif
                </p>
            </div>
            <span @class([
                'inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset',
                $statusClasses,
            ])>
                {{ $status->label() }}
            </span>
        </div>

        <div class="space-y-4 p-5 sm:p-6">
            @if ($activeAdmission->canBeApproved() && ! auth()->user()?->can('approve', $activeAdmission))
                <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-500/30 dark:bg-sky-500/10">
                    <p class="text-sm font-medium text-sky-900 dark:text-sky-100">Waiting for approval</p>
                    <p class="mt-1 text-sm text-sky-800 dark:text-sky-200">
                        The admission form is submitted. An authorized officer must verify documents and approve to create the roll number.
                    </p>
                </div>
            @elseif ($activeAdmission->status->value === 'submitted' && ! $activeAdmission->hasReviewableSubmission())
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="text-sm font-medium text-amber-900 dark:text-amber-100">Waiting for admission form</p>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                        Complete academic details and uploads below, or ask the student to submit via the portal.
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
                <div class="flex flex-col gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                    <p class="text-sm text-warning-900 dark:text-warning-200">
                        Verify the form and documents below, then approve or return for correction.
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
        </div>
    </div>
@endif
