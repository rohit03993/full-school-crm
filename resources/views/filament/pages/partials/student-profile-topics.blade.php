<div class="space-y-4">
    @if (! ($topicsTabLoaded ?? false))
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading topics…</p>
    @else
        <p class="text-xs text-gray-500 dark:text-gray-400">Topics this student will cover. Minutes stay off this screen.</p>

        @if (($groups ?? []) === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">No final section plan for this class yet.</p>
        @else
            @foreach ($groups as $group)
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-white/5">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group['subject'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Faculty: {{ $group['faculty'] }}</p>
                    </div>
                    <div class="space-y-4 px-4 py-3">
                        @foreach ($group['chapters'] as $chapter)
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $chapter['name'] }}</p>
                                <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($chapter['topics'] as $topic)
                                        <li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
                                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ $topic['name'] }}</span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                DPP {{ $topic['dpp_count'] }}
                                                · Quiz/PYQs {{ $topic['quiz_count'] }}
                                                · Test {{ $topic['test_count'] }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    @endif
</div>

