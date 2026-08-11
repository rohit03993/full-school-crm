<div wire:init="loadCertificatesTab" class="space-y-4">
    @if ($canIssue)
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-5">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Issue certificate</h3>
            <div class="fi-crm-form mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Type</label>
                    <x-crm.select wire:model="profileCertificateType" class="mt-2">
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-crm.select>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Remarks (optional)</label>
                    <input type="text" wire:model="profileCertificateRemarks" class="fi-crm-input mt-2 block w-full" maxlength="1000" />
                </div>
                <div class="flex items-end">
                    <button
                        type="button"
                        wire:click="issueProfileCertificate"
                        class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                    >
                        Issue PDF
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if (! $certificatesTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading certificates…</p>
    @elseif ($certificates->isEmpty())
        <div class="fi-section rounded-xl px-4 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">No certificates issued for this student yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($certificates as $certificate)
                <div class="rounded-xl border border-gray-100 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:px-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $certificate->type?->label() }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $certificate->issued_on?->format('d M Y') }}
                                · {{ $certificate->serial_number }}
                                @if ($certificate->issuedBy)
                                    · {{ $certificate->issuedBy->name }}
                                @endif
                            </p>
                            @if (filled($certificate->remarks))
                                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $certificate->remarks }}</p>
                            @endif
                        </div>
                        @if ($certificate->hasPdf())
                            <div class="flex flex-wrap gap-2 text-sm">
                                <a href="{{ route('admin.certificates.preview', $certificate) }}" target="_blank" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Preview</a>
                                <a href="{{ route('admin.certificates.download', $certificate) }}" class="font-semibold text-gray-700 hover:underline dark:text-gray-300">Download</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
