<x-filament-panels::page>
    @php
        /** @var \App\Models\Admission $record */
        $admission = $record;
    @endphp

    <div class="space-y-4">
        @if ($admission->hasReviewableSubmission())
            @include('filament.partials.admission-form-review', [
                'admission' => $admission,
                'student' => $admission->student,
            ])
        @else
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-white/20">
                <p class="text-sm text-gray-600 dark:text-gray-400">Admission form not submitted yet.</p>
                <p class="mt-2 text-xs text-gray-500">Staff or the student still needs to fill academic details and upload documents.</p>
            </div>
        @endif

        @if ($admission->canBeApproved())
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                Review the admission form above, then use <strong>Approve Admission</strong> or <strong>Return for Correction</strong> in the page header.
            </div>
        @endif
    </div>
</x-filament-panels::page>
