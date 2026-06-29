@if (! $admission)
    <section class="portal-card p-5">
        <p class="font-semibold text-navy-800">No admission started</p>
        <p class="mt-2 text-sm leading-relaxed text-navy-600">Your enquiry is with the institute. Contact the office when you are ready to proceed with admission.</p>
    </section>
@else
    @php
        $status = $admission->status;
        $statusTone = match ($status->value) {
            'submitted', 'rejected' => 'amber',
            'verification_pending' => 'sky',
            'approved' => 'green',
            default => 'navy',
        };
    @endphp

    <section @class([
        'rounded-2xl border p-4 shadow-sm sm:p-5',
        'border-amber-200 bg-amber-50' => $statusTone === 'amber',
        'border-sky-200 bg-sky-50' => $statusTone === 'sky',
        'border-emerald-200 bg-emerald-50' => $statusTone === 'green',
        'portal-card' => $statusTone === 'navy',
    ])>
        <p class="text-xs font-bold uppercase tracking-wider text-navy-500">Admission status</p>
        <p @class([
            'mt-1 text-lg font-bold',
            'text-amber-900' => $statusTone === 'amber',
            'text-sky-900' => $statusTone === 'sky',
            'text-emerald-900' => $statusTone === 'green',
            'text-navy-900' => $statusTone === 'navy',
        ])>{{ $status->label() }}</p>
        <p class="mt-2 font-mono text-sm font-semibold text-brand-700">{{ $admission->admission_number }}</p>
        <p class="mt-1 text-sm text-navy-600">{{ $admission->enquiry?->course?->name }}</p>

        @if ($status->value === 'verification_pending')
            <p class="mt-3 text-sm leading-relaxed text-sky-900">Your form has been submitted. Our team is verifying your documents.</p>
        @elseif ($status->value === 'submitted' || $status->value === 'rejected')
            <p class="mt-3 text-sm leading-relaxed text-amber-900">Please complete and submit the admission form below.</p>
        @elseif ($status->value === 'approved' && ! $student->activeEnrollment)
            <p class="mt-3 text-sm leading-relaxed text-emerald-900">Admission approved — enrollment is being finalized.</p>
        @endif

        @if ($admission->staff_remarks)
            <div class="mt-4 rounded-xl border border-amber-300/50 bg-white/70 p-3.5 text-sm text-amber-950">
                <p class="font-semibold">Staff remarks</p>
                <p class="mt-1 leading-relaxed">{{ $admission->staff_remarks }}</p>
            </div>
        @endif
    </section>

    @if ($canFillForm)
        <form method="POST" action="{{ route('portal.admission.submit') }}" enctype="multipart/form-data" class="portal-card space-y-4 p-4 sm:space-y-5 sm:p-5">
            @csrf

            <div>
                <h2 class="font-display text-lg font-bold text-navy-900">Admission form</h2>
                <p class="mt-0.5 text-sm text-navy-500">Fill all fields and upload required documents.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                @foreach ([
                    ['tenth_board', '10th Board'],
                    ['tenth_percentage', '10th %'],
                    ['twelfth_board', '12th Board'],
                    ['twelfth_percentage', '12th %'],
                    ['graduation', 'Graduation'],
                    ['graduation_percentage', 'Graduation %'],
                ] as [$name, $label])
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-navy-800">{{ $label }}</label>
                        <input type="{{ str_contains($name, 'percentage') ? 'number' : 'text' }}" name="{{ $name }}"
                            value="{{ old($name, $admission->{$name}) }}" step="0.01"
                            class="portal-input">
                    </div>
                @endforeach
            </div>

            <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                @foreach ([
                    ['photo', 'Student Photo'],
                    ['aadhaar', 'Aadhaar Card'],
                    ['marksheet', 'Marksheet'],
                    ['signature', 'Signature'],
                ] as [$name, $label])
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-navy-800">{{ $label }} *</label>
                        <input type="file" name="{{ $name }}" accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-navy-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-navy-800">
                        @php $existing = $admission->documents->first(fn ($d) => $d->type->value === $name); @endphp
                        @if ($existing)
                            <p class="mt-1 text-xs text-navy-500">Uploaded: {{ $existing->original_filename }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            @error('documents')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('admission')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <button type="submit" class="touch-manipulation w-full rounded-xl bg-brand-500 py-3 text-sm font-bold text-navy-950 shadow-sm transition hover:bg-brand-400 active:scale-[0.99]">
                Submit admission form
            </button>
        </form>
    @elseif (! $student->activeEnrollment)
        <section class="portal-card p-4 text-sm leading-relaxed text-navy-600 sm:p-5">
            @if ($status->value === 'verification_pending')
                Your admission form is under verification. You cannot edit it until staff reviews it.
            @else
                Your admission form is with the institute for processing. We will contact you if anything is needed.
            @endif
        </section>
    @endif
@endif
