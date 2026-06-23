@php
    use App\Filament\Pages\StudentProfilePage;
@endphp

@if (($searchResults ?? null)?->isNotEmpty())
    @php
        $count = $searchResults->count();
        $searchedName = $searchedName ?? '';
        $sameName = $searchResults->pluck('name')->unique()->count() === 1;
    @endphp

    <div class="fi-student-search-results flex min-h-0 flex-col overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="shrink-0 border-b border-gray-100 px-3 py-2.5 dark:border-white/10 sm:px-4">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-gray-950 dark:text-white">
                        @if ($sameName && $count > 1)
                            {{ $count }} × “{{ $searchResults->first()->name }}” — pick one
                        @else
                            {{ $count }} {{ str('match')->plural($count) }} for “{{ $searchedName }}”
                        @endif
                    </p>
                    @if ($sameName && $count > 1)
                        <p class="truncate text-[11px] text-gray-500 dark:text-gray-400">Confirm by mobile or father’s name</p>
                    @else
                        <p class="truncate text-[11px] text-gray-500 dark:text-gray-400">Tap a row to open the student profile</p>
                    @endif
                </div>
                <span class="shrink-0 rounded-md bg-primary-500/10 px-2 py-0.5 text-[10px] font-bold uppercase text-primary-700 dark:text-primary-300">
                    Tap
                </span>
            </div>
        </div>

        <div class="fi-student-search-results-list min-h-0 flex-1 divide-y divide-gray-100 overflow-y-auto dark:divide-white/10">
            @foreach ($searchResults as $student)
                @php
                    $profileUrl = StudentProfilePage::getUrl(['record' => $student->id]);
                @endphp

                <a
                    href="{{ $profileUrl }}"
                    wire:navigate
                    wire:key="student-search-{{ $student->id }}"
                    class="group flex w-full touch-manipulation gap-2.5 px-3 py-2.5 text-left transition hover:bg-primary-500/[0.04] active:bg-primary-500/[0.08] sm:gap-3 sm:px-4"
                >
                    @include('filament.pages.partials.student-avatar', ['student' => $student])

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <span class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $student->name }}</span>
                            <span class="font-mono text-sm font-semibold text-primary-600 dark:text-primary-400">{{ $student->mobile }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-[11px] text-gray-500 dark:text-gray-400">
                            @if ($student->father_name)
                                Father: {{ $student->father_name }}
                            @endif
                            @if ($student->father_name && ($student->city || $student->latestEnquiry?->course))
                                ·
                            @endif
                            @if ($student->city)
                                {{ $student->city }}
                            @endif
                            @if ($student->latestEnquiry?->course)
                                @if ($student->city) · @endif
                                {{ $student->latestEnquiry->course->name }}
                            @endif
                        </p>
                    </div>

                    <x-filament::icon
                        icon="heroicon-m-chevron-right"
                        class="h-4 w-4 shrink-0 self-center text-gray-300 group-hover:text-primary-500 dark:text-gray-600"
                    />
                </a>
            @endforeach
        </div>
    </div>
@endif
