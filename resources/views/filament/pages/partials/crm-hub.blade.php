{{--
  Shared module hub layout.
  Expected: $heading, $intro, $cards (list of title/description/url/badge?/tone?), $footer?
--}}
<div class="space-y-5">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-col gap-1">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">{{ $heading }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $intro }}</p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <a
                href="{{ $card['url'] }}"
                @class([
                    'group rounded-2xl border p-5 transition hover:-translate-y-0.5 hover:shadow-md',
                    'border-primary-200 bg-primary-50 hover:border-primary-300 dark:border-primary-500/30 dark:bg-primary-500/10' => ($card['tone'] ?? null) === 'primary',
                    'border-gray-200 bg-white hover:border-amber-300 dark:border-white/10 dark:bg-gray-900' => ($card['tone'] ?? null) !== 'primary',
                ])
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        @if (! empty($card['badge']))
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-1 text-xs font-bold text-white',
                                'bg-primary-600' => ($card['tone'] ?? null) === 'primary',
                                'bg-amber-500' => ($card['tone'] ?? null) !== 'primary',
                            ])>{{ $card['badge'] }}</span>
                        @endif
                        <h3 @class([
                            'text-base font-bold text-gray-950 dark:text-white',
                            'mt-3' => ! empty($card['badge']),
                        ])>{{ $card['title'] }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $card['description'] }}</p>
                    </div>
                    <span @class([
                        'text-xl transition group-hover:translate-x-1',
                        'text-primary-600 dark:text-primary-400' => ($card['tone'] ?? null) === 'primary',
                        'text-amber-600 dark:text-amber-400' => ($card['tone'] ?? null) !== 'primary',
                    ])>→</span>
                </div>
            </a>
        @endforeach
    </div>

    @if (! empty($footer))
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            {!! $footer !!}
        </div>
    @endif
</div>
