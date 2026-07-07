<div class="space-y-8">
    <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-white via-white to-primary-50/40 p-5 dark:border-white/10 dark:from-gray-900 dark:via-gray-900 dark:to-primary-500/5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-folder-open" class="h-6 w-6" />
                </span>
                <div>
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">Documents</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                        Admission form, academic details, photo, ID proofs, and marksheets — everything in one place for review and approval.
                    </p>
                </div>
            </div>
            @if ($showAdmissionSection && $admissionTabLoaded && $activeAdmission)
                <div class="rounded-xl bg-white/80 px-4 py-3 text-sm ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Admission no.</p>
                    <p class="mt-0.5 font-mono font-semibold text-gray-950 dark:text-white">{{ $activeAdmission->admission_number }}</p>
                </div>
            @endif
        </div>
    </div>

    @if ($showAdmissionSection)
        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-4 w-4" />
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Admission form</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Academic details, fee plan, and approval workflow</p>
                </div>
            </div>

            @include('filament.pages.partials.student-profile-admission-section', [
                'admissionTabLoaded' => $admissionTabLoaded,
                'activeAdmission' => $activeAdmission,
                'record' => $record,
            ])
        </section>
    @endif

    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-o-photo" class="h-4 w-4" />
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Uploaded files</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Photo, Aadhaar, marksheets, signature &amp; other documents</p>
                </div>
            </div>
            @if ($documentsTabLoaded && $documents->isNotEmpty())
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    {{ $documents->count() }} {{ str('file')->plural($documents->count()) }}
                </span>
            @endif
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6">
            @if (! $documentsTabLoaded)
                <p class="text-sm text-gray-500 dark:text-gray-400">Loading documents…</p>
            @else
                @include('filament.pages.partials.document-tiles-grid', [
                    'documents' => $documents,
                    'emptyMessage' => 'No documents uploaded yet. Complete the admission form above to add photo and ID proofs.',
                ])
            @endif
        </div>
    </section>
</div>
