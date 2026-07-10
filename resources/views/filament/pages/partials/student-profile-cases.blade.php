@php
    use App\Enums\StudentCaseStatus;
@endphp

<div class="space-y-4">
    @if ($canOpenCaseAsAdmin ?? false)
        @if ($showOpenCaseForm ?? false)
            <form wire:submit="submitOpenCase" class="space-y-3 rounded-2xl border border-primary-200 bg-primary-50/50 p-4 shadow-sm ring-1 ring-primary-200/60 dark:border-primary-500/20 dark:bg-primary-500/5 dark:ring-primary-500/20">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Open case (admin)</p>
                        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">Create a support case and assign it to staff. Only Super Admin can use this.</p>
                    </div>
                    <button type="button" wire:click="cancelOpenCaseForm" class="text-xs font-semibold text-gray-500">Cancel</button>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Case type</label>
                    <x-crm.select wire:model="openCaseType" class="mt-1" required>
                        @foreach ($caseTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-crm.select>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Title</label>
                    <input type="text" wire:model="openCaseTitle" required class="fi-crm-input mt-1 block w-full" placeholder="Short summary, e.g. Chemistry teacher complaint" />
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Details (optional)</label>
                    <textarea wire:model="openCaseSummary" rows="2" class="fi-crm-input mt-1 block w-full" placeholder="Extra context for the assignee"></textarea>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Assign to</label>
                    <x-crm.select wire:model="openCaseAssigneeId" class="mt-1" required>
                        <option value="">Select staff…</option>
                        @foreach ($staffOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-crm.select>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Handoff note</label>
                    <textarea wire:model="openCaseHandoffNote" rows="2" required class="fi-crm-input mt-1 block w-full" placeholder="What should the assignee do?"></textarea>
                </div>

                <button type="submit" class="inline-flex rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                    Open case &amp; assign
                </button>
            </form>
        @else
            <button
                type="button"
                wire:click="openOpenCaseForm"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 sm:w-auto"
            >
                Open case
            </button>
        @endif
    @endif

    @if (! $casesTabLoaded)
        <div class="rounded-xl bg-white px-4 py-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            Loading cases…
        </div>
    @elseif ($cases->isEmpty())
        <div class="rounded-xl bg-white px-4 py-10 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-base font-semibold text-gray-950 dark:text-white">No cases yet</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                @if ($canOpenCaseAsAdmin ?? false)
                    Use <strong>Open case</strong> above to assign follow-up work to staff, or staff can open a case when closing a campus meeting.
                @else
                    When a campus visit cannot be resolved on the spot, staff can open a case from the close-meeting flow.
                @endif
            </p>
        </div>
    @else
        @php
            $openCases = $cases->filter(fn ($case) => $case->status === StudentCaseStatus::Open);
            $closedCases = $cases->filter(fn ($case) => $case->status === StudentCaseStatus::Closed);
        @endphp

        @if ($openCases->isNotEmpty())
            <div class="space-y-3">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Active cases</h3>
                @foreach ($openCases as $case)
                    @include('filament.pages.partials.student-case-card', ['case' => $case, 'expanded' => $expandedCaseId === $case->id])
                @endforeach
            </div>
        @endif

        @if ($closedCases->isNotEmpty())
            <div class="space-y-3">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Closed cases</h3>
                @foreach ($closedCases as $case)
                    @include('filament.pages.partials.student-case-card', ['case' => $case, 'expanded' => $expandedCaseId === $case->id])
                @endforeach
            </div>
        @endif
    @endif
</div>
