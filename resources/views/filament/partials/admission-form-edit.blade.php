@php
    use App\Enums\DocumentType;

    $photo = $activeAdmission->documentForType(DocumentType::Photo);
@endphp

<form wire:submit="submitAdmissionForm" class="space-y-4">
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 bg-gradient-to-r from-primary-50 via-white to-white px-4 py-4 dark:border-white/10 dark:from-primary-500/10 dark:via-gray-900 sm:px-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">Admission Form</p>
            <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $record->name }}</h3>
            <p class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $activeAdmission->admission_number }} · {{ $activeAdmission->enquiry?->course?->name }}</p>
        </div>

        {{-- Photo + personal (read-only) --}}
        <div class="grid gap-6 border-b border-gray-100 p-4 dark:border-white/10 sm:p-6 lg:grid-cols-[240px_minmax(0,1fr)]">
            <div class="flex flex-col items-center">
                <p class="mb-3 w-full text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Student Photograph <span class="text-danger-600">*</span>
                </p>
                <div class="w-full max-w-[200px]">
                    @if ($photo && $photo->isImage())
                        <img
                            src="{{ $photo->previewUrl() }}"
                            alt="Current photo"
                            class="mb-3 aspect-[3/4] w-full rounded-xl object-cover shadow-md ring-4 ring-white dark:ring-gray-800"
                        />
                    @else
                        <div class="mb-3 flex aspect-[3/4] w-full items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 dark:border-white/20 dark:bg-white/5">
                            <p class="px-2 text-center text-xs text-gray-400">Passport-size photo</p>
                        </div>
                    @endif
                    <input
                        type="file"
                        wire:model="uploadPhoto"
                        accept=".jpg,.jpeg,.png"
                        class="fi-input block w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-primary-50 file:px-2 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700 dark:file:bg-primary-500/10 dark:file:text-primary-300"
                    />
                    @error('uploadPhoto')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                    @if ($photo)
                        <p class="mt-1 text-center text-[10px] text-gray-400">Current: {{ $photo->original_filename }}</p>
                    @endif
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Personal Details</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">From enquiry — edit on Overview if needed.</p>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    @foreach ([
                        ['Full Name', $record->name],
                        ['Father\'s Name', $record->father_name],
                        ['Date of Birth', $record->date_of_birth?->format('d M Y')],
                        ['Gender', $record->gender?->label()],
                        ['Mobile', $record->mobile],
                        ['Email', $record->email],
                    ] as [$label, $value])
                        <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                            <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        @if ($activeAdmission->course_fee !== null)
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fee Summary</p>
                @include('filament.pages.partials.student-profile-admission-fees', [
                    'activeAdmission' => $activeAdmission,
                ])
            </div>
        @endif

        <div class="border-b border-gray-100 p-4 dark:border-white/10 sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Academic Qualifications</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter board names and percentages. Graduation is optional.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-100 dark:bg-white/5 dark:ring-white/10">
                    <p class="text-sm font-bold text-primary-700 dark:text-primary-400">Class 10th</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <x-crm.text-input label="Board" model="tenthBoard" placeholder="e.g. CBSE" />
                        <x-crm.text-input label="Percentage" model="tenthPercentage" type="number" step="0.01" placeholder="e.g. 85.5" />
                    </div>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-100 dark:bg-white/5 dark:ring-white/10">
                    <p class="text-sm font-bold text-primary-700 dark:text-primary-400">Class 12th</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <x-crm.text-input label="Board" model="twelfthBoard" placeholder="e.g. ICSE" />
                        <x-crm.text-input label="Percentage" model="twelfthPercentage" type="number" step="0.01" placeholder="e.g. 90" />
                    </div>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-100 md:col-span-2 dark:bg-white/5 dark:ring-white/10">
                    <p class="text-sm font-bold text-primary-700 dark:text-primary-400">Graduation <span class="text-xs font-normal text-gray-500">(optional)</span></p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <x-crm.text-input label="College / University" model="graduation" placeholder="If applicable" />
                        <x-crm.text-input label="Percentage" model="graduationPercentage" type="number" step="0.01" placeholder="If applicable" />
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Supporting Documents</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG or PDF · max 5 MB each</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['aadhaar', 'Aadhaar Card', 'uploadAadhaar'],
                    ['marksheet', 'Marksheet', 'uploadMarksheet'],
                    ['signature', 'Signature', 'uploadSignature'],
                ] as [$type, $label, $wireModel])
                    @php $existing = $activeAdmission->documents->first(fn ($doc) => $doc->type->value === $type); @endphp
                    <div class="rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                        <div class="border-b border-gray-100 px-3 py-2 dark:border-white/10">
                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $label }} <span class="text-danger-600">*</span></p>
                        </div>
                        <div class="p-3">
                            @if ($existing && $existing->isImage())
                                <img src="{{ $existing->previewUrl() }}" alt="{{ $label }}" class="mb-2 max-h-24 w-full rounded-lg object-contain" />
                            @endif
                            <input
                                type="file"
                                wire:model="{{ $wireModel }}"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="fi-input block w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-primary-50 file:px-2 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700 dark:file:bg-primary-500/10"
                            />
                            @error($wireModel)<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                            @if ($existing)
                                <p class="mt-1 text-[10px] text-gray-400">Uploaded: {{ $existing->original_filename }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @error('documents')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror

    <x-filament::button type="submit" class="w-full sm:w-auto" wire:loading.attr="disabled">
        Submit Admission Form
    </x-filament::button>
</form>
