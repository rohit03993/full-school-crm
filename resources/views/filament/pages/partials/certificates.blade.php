@php
    use App\Filament\Pages\StudentProfilePage;
@endphp

<div class="mx-auto max-w-6xl space-y-4 pb-24 lg:pb-6">
    @if ($canIssue)
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Issue certificate</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Search an enrolled student, choose type, then issue a PDF.</p>

            <div class="fi-crm-form mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Student</label>
                    <div class="mt-2 flex gap-2">
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="issueStudentSearch"
                            placeholder="Name or mobile"
                            class="fi-crm-input block w-full"
                            @disabled($this->issueStudentId)
                        />
                        @if ($this->issueStudentId)
                            <button type="button" wire:click="clearIssueStudent" class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-300 dark:ring-white/10">
                                Clear
                            </button>
                        @endif
                    </div>
                    @if (! empty($issueSuggestions))
                        <ul class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg bg-white shadow-lg ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                            @foreach ($issueSuggestions as $suggestion)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="selectIssueStudent({{ $suggestion->id }})"
                                        class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-white/5"
                                    >
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $suggestion->name }}</span>
                                        <span class="text-xs text-gray-500">
                                            {{ $suggestion->mobile }}
                                            @if ($suggestion->activeEnrollment)
                                                · {{ $suggestion->activeEnrollment->enrollment_number }}
                                            @endif
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Type</label>
                    <x-crm.select wire:model="issueType" class="mt-2">
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-crm.select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Issue date</label>
                    <input type="date" wire:model="issueDate" class="fi-crm-input mt-2 block w-full" />
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Remarks (optional)</label>
                    <input type="text" wire:model="issueRemarks" class="fi-crm-input mt-2 block w-full" maxlength="1000" />
                </div>
                <div class="flex items-end">
                    <button
                        type="button"
                        wire:click="issueCertificate"
                        class="w-full rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                    >
                        Issue PDF
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-crm-form grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Type</label>
                <x-crm.select wire:model.live="typeFilter" class="mt-2">
                    <option value="">All types</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select>
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Search</label>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Serial, student name, or mobile"
                    class="fi-crm-input mt-2 block w-full"
                />
            </div>
        </div>
    </div>

    <div class="fi-section overflow-hidden rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        @if ($certificates->isEmpty())
            <div class="px-4 py-10 text-center sm:px-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">No certificates issued yet.</p>
            </div>
        @else
            <x-crm.responsive-table>
                <table class="min-w-full divide-y divide-gray-100 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Issued</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Student</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Serial</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">By</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($certificates as $certificate)
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $certificate->issued_on?->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <a
                                        href="{{ StudentProfilePage::getUrl(['record' => $certificate->student_id]) }}"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ $certificate->student?->name }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $certificate->type?->label() }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-mono text-gray-600 dark:text-gray-400">
                                    {{ $certificate->serial_number }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $certificate->issuedBy?->name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    @if ($certificate->hasPdf())
                                        <a href="{{ route('admin.certificates.preview', $certificate) }}" target="_blank" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Preview</a>
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <a href="{{ route('admin.certificates.download', $certificate) }}" class="font-semibold text-gray-700 hover:underline dark:text-gray-300">Download</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
            <div class="border-t border-gray-100 px-4 py-3 dark:border-white/10">
                {{ $certificates->links() }}
            </div>
        @endif
    </div>
</div>
