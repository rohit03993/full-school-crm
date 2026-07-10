@if (! empty($profile['open_cases'] ?? []))
    <div class="space-y-2">
        @foreach ($profile['open_cases'] as $case)
            <div class="rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-500/20">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">Open case</p>
                <p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $case['case_number'] }} — {{ $case['title'] }}
                </p>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                    {{ $case['type_label'] }} · assigned to {{ $case['assignee_name'] }}
                    @if ($case['opened_at_label'])
                        · opened {{ $case['opened_at_label'] }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>
@endif
