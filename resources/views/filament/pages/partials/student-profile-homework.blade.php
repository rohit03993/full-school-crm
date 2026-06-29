@if (! $homeworkTabLoaded)
    <p class="text-sm text-gray-500 dark:text-gray-400">Loading homework…</p>
@elseif ($assignments->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No homework assigned to this student&apos;s batch yet.</p>
@else
    <div class="space-y-3">
        @foreach ($assignments as $assignment)
            @php
                $viewed = $assignment->views->isNotEmpty();
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $assignment->title }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $assignment->batch?->name }} · {{ $assignment->published_at?->format('d M Y') }}
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $viewed ? 'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' }}">
                        {{ $viewed ? 'Viewed in portal' : 'Not viewed' }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-200 line-clamp-3">{{ $assignment->description }}</p>
            </div>
        @endforeach
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Students open homework in the portal:
            <a href="{{ $portalUrl }}" target="_blank" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $portalUrl }}</a>
        </p>
    </div>
@endif
