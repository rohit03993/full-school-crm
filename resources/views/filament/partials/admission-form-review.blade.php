@php
    use App\Enums\DocumentType;

    $student = $student ?? $admission->student;
    $photo = $admission->documentForType(DocumentType::Photo);
    $aadhaar = $admission->documentForType(DocumentType::Aadhaar);
    $marksheet = $admission->documentForType(DocumentType::Marksheet);
    $signature = $admission->documentForType(DocumentType::Signature);
@endphp

<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    {{-- Form header --}}
    <div class="border-b border-gray-100 bg-gradient-to-r from-primary-50 via-white to-white px-4 py-4 dark:border-white/10 dark:from-primary-500/10 dark:via-gray-900 dark:to-gray-900 sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">Admission Form</p>
                <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $student?->name ?? 'Student' }}</h3>
                @if ($admission->submitted_at)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Submitted {{ $admission->submitted_at->format('d M Y, h:i A') }}
                    </p>
                @endif
            </div>
            <span class="inline-flex w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/10">
                {{ $admission->status->label() }}
            </span>
        </div>
    </div>

    {{-- Photo + personal details --}}
    <div class="grid gap-6 border-b border-gray-100 p-4 dark:border-white/10 sm:p-6 lg:grid-cols-[240px_minmax(0,1fr)]">
        <div class="flex flex-col items-center">
            <p class="mb-3 w-full text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Student Photograph
            </p>
            <div class="relative w-full max-w-[200px]">
                @if ($photo && $photo->isImage())
                    <button
                        type="button"
                        class="js-media-preview-trigger block w-full cursor-zoom-in"
                        data-preview-url="{{ $photo->previewUrl() }}"
                        data-preview-title="Student photo"
                        data-preview-pdf="0"
                    >
                        <img
                            src="{{ $photo->previewUrl() }}"
                            alt="Student photo"
                            class="aspect-[3/4] w-full rounded-xl object-cover shadow-md ring-4 ring-white dark:ring-gray-800"
                        />
                    </button>
                @else
                    <div class="flex aspect-[3/4] w-full items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 dark:border-white/20 dark:bg-white/5">
                        <div class="text-center">
                            <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <p class="mt-2 text-xs text-gray-400">No photo</p>
                        </div>
                    </div>
                @endif
            </div>
            @if ($photo)
                <div class="mt-3 flex w-full max-w-[200px] flex-wrap justify-center gap-2">
                    @if ($photo->isPreviewableInBrowser())
                        <x-crm.media-preview-button
                            :url="$photo->previewUrl()"
                            title="Student photo"
                            label="View full"
                            class="px-0 py-0 text-xs text-primary-600 ring-0 bg-transparent hover:bg-transparent dark:text-primary-400"
                        />
                    @endif
                    <a href="{{ $photo->downloadUrl() }}" class="text-xs font-semibold text-gray-600 hover:underline dark:text-gray-400">Download</a>
                </div>
            @endif
        </div>

        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Personal Details</p>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                @foreach ([
                    ['Full Name', $student?->name],
                    ['Father\'s Name', $student?->father_name],
                    ['Date of Birth', $student?->date_of_birth?->format('d M Y')],
                    ['Gender', $student?->gender?->label()],
                    ['Mobile', $student?->mobile],
                    ['Email', $student?->email],
                    ['Admission No.', $admission->admission_number],
                    ['Course', $admission->enquiry?->course?->name],
                ] as [$label, $value])
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                        <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach
                @if (filled($student?->address) || filled($student?->city))
                    <div class="rounded-lg bg-gray-50 px-3 py-2 sm:col-span-2 dark:bg-white/5">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Address</dt>
                        <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">
                            {{ collect([$student?->address, $student?->city, $student?->state, $student?->pincode])->filter()->implode(', ') ?: '—' }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- Fee summary --}}
    @if ($admission->course_fee !== null)
        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fee Summary</p>
            @include('filament.partials.admission-fee-review-summary', [
                'activeAdmission' => $admission,
            ])
        </div>
    @endif

    @if ($admission->enrollment)
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
            <p class="text-sm font-semibold text-success-600 dark:text-success-400">
                Enrolled · {{ \App\Support\StudentLabels::rollNumberLabel() }}: <span class="font-mono">{{ $admission->enrollment->enrollment_number }}</span>
            </p>
        </div>
    @endif

    @if ($admission->staff_remarks)
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
            <p class="rounded-lg bg-warning-50 p-3 text-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
                <span class="font-semibold">Staff remarks:</span> {{ $admission->staff_remarks }}
            </p>
        </div>
    @endif

    {{-- Academic qualifications --}}
    <div class="border-b border-gray-100 p-4 dark:border-white/10 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Academic Qualifications</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-100 dark:bg-white/5 dark:ring-white/10">
                <p class="text-sm font-bold text-primary-700 dark:text-primary-400">Class 10th</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[10px] font-semibold uppercase text-gray-400">Board</dt>
                        <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $admission->tenth_board ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase text-gray-400">Percentage</dt>
                        <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">
                            {{ $admission->tenth_percentage !== null ? number_format((float) $admission->tenth_percentage, 2).'%' : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-100 dark:bg-white/5 dark:ring-white/10">
                <p class="text-sm font-bold text-primary-700 dark:text-primary-400">Class 12th</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[10px] font-semibold uppercase text-gray-400">Board</dt>
                        <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $admission->twelfth_board ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase text-gray-400">Percentage</dt>
                        <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">
                            {{ $admission->twelfth_percentage !== null ? number_format((float) $admission->twelfth_percentage, 2).'%' : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
            @if (filled($admission->graduation) || $admission->graduation_percentage !== null)
                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-100 md:col-span-2 dark:bg-white/5 dark:ring-white/10">
                    <p class="text-sm font-bold text-primary-700 dark:text-primary-400">Graduation</p>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[10px] font-semibold uppercase text-gray-400">College / University</dt>
                            <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $admission->graduation ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-semibold uppercase text-gray-400">Percentage</dt>
                            <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">
                                {{ $admission->graduation_percentage !== null ? number_format((float) $admission->graduation_percentage, 2).'%' : '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    </div>

    {{-- Supporting documents --}}
    <div class="p-4 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Supporting Documents</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Aadhaar, marksheet and signature as submitted with this form.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-crm.admission-document-tile :document="$aadhaar" label="Aadhaar Card" />
            <x-crm.admission-document-tile :document="$marksheet" label="Marksheet" />
            <x-crm.admission-document-tile :document="$signature" label="Signature" variant="signature" />
        </div>
    </div>
</div>
