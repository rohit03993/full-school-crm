@if (! empty($examMarksSections))
    <section class="space-y-3">
        <div>
            <h2 class="font-display text-lg font-bold text-navy-900">Test &amp; exam marks</h2>
            <p class="mt-0.5 text-sm text-navy-500">Declared results — visible after your institute publishes them online</p>
        </div>

        @if (! empty($publishedResults) && $publishedResults->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($publishedResults as $sheet)
                    @php($declaration = $sheet->resultDeclaration)
                    <div class="portal-card p-4">
                        <p class="text-sm font-semibold text-navy-900">{{ $declaration?->test_name ?? 'Result' }}</p>
                        <p class="mt-1 text-xs text-navy-500">
                            Declared {{ $declaration?->declaration_date?->format('d M Y') ?? '—' }}
                            @if ($declaration?->marksheet_issue_date)
                                · Marksheet {{ $declaration->marksheet_issue_date->format('d M Y') }}
                            @endif
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                            @if ($sheet->percentage !== null)
                                <span class="rounded-full bg-brand-50 px-2.5 py-0.5 font-semibold text-brand-800">{{ number_format((float) $sheet->percentage, 1) }}%</span>
                            @endif
                            @if (filled($sheet->division))
                                <span class="rounded-full bg-navy-100 px-2.5 py-0.5 font-medium text-navy-700">{{ $sheet->division }}</span>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-navy-400">Collect your official marksheet from the institute office.</p>
                    </div>
                @endforeach
            </div>
        @endif

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
