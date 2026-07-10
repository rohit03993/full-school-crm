@php
    use App\Enums\StudentCaseStatus;
@endphp

<div class="space-y-4">
    @if (! $casesTabLoaded)
        <div class="rounded-xl bg-white px-4 py-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            Loading cases…
        </div>
    @elseif ($cases->isEmpty())
        <div class="rounded-xl bg-white px-4 py-10 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-base font-semibold text-gray-950 dark:text-white">No cases yet</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                When a campus visit cannot be resolved on the spot, staff can open a case from the close-meeting flow.
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
