@if (($searchResults ?? null)?->isNotEmpty())
    <div class="fi-section mt-4 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ $searchResults->count() }} matches — tap to open
            </h3>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($searchResults as $student)
                <button
                    type="button"
                    wire:click="openStudent({{ $student->id }})"
                    class="flex w-full min-h-[4.5rem] touch-manipulation items-center justify-between gap-3 px-4 py-4 text-left transition active:bg-gray-100 hover:bg-gray-50 dark:active:bg-white/10 dark:hover:bg-white/5 sm:px-6"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $student->name }}</p>
                        <p class="mt-0.5 text-sm font-medium text-primary-600 dark:text-primary-400">
                            {{ $student->mobile }}
                        </p>
                        @if ($student->city)
                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $student->city }}</p>
                        @endif
                        @if ($student->latestEnquiry)
                            <p class="mt-1 truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                {{ $student->latestEnquiry->enquiry_number }}
                                @if ($student->latestEnquiry->course)
                                    · {{ $student->latestEnquiry->course->name }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-lg bg-primary-50 px-2.5 py-1.5 text-xs font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                        Open
                    </span>
                </button>
            @endforeach
        </div>
    </div>
@endif
