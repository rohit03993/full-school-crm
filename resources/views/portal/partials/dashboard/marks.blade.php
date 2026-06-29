@if (! empty($examMarksSections))
    <section class="space-y-3">
        <div>
            <h2 class="font-display text-lg font-bold text-navy-900">Test &amp; exam marks</h2>
            <p class="mt-0.5 text-sm text-navy-500">Marks recorded by your institute</p>
        </div>

        @foreach ($examMarksSections as $section)
            <div class="portal-card overflow-hidden">
                <div class="border-b border-navy-100 px-4 py-3.5 sm:px-5">
                    <h3 class="font-semibold text-navy-900">{{ $section['label'] }}</h3>
                </div>
                <div class="relative">
                    <p class="px-3 pt-2 text-[10px] font-semibold uppercase tracking-wide text-navy-400 sm:hidden">Swipe for all subjects →</p>
                    <div class="overflow-x-auto px-2 py-3 sm:px-4">
                        @include('portal.partials.exam-marks-matrix', ['matrix' => $section['matrix']])
                    </div>
                </div>
            </div>
        @endforeach
    </section>
@else
    <section class="portal-card p-8 text-center">
        <p class="text-sm font-medium text-navy-700">No marks published yet</p>
        <p class="mt-1 text-sm text-navy-500">Your test and exam results will appear here when available.</p>
    </section>
@endif
