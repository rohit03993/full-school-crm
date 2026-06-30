@php
    $initials = collect(preg_split('/\s+/', $institute['name'] ?? 'School CRM'))
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('') ?: 'SC';
    $navLinks = [
        ['label' => 'Home', 'url' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => 'Courses', 'url' => route('courses'), 'active' => request()->routeIs('courses')],
        ['label' => 'Gallery', 'url' => route('home').'#gallery', 'active' => false],
        ['label' => 'Contact', 'url' => route('contact'), 'active' => request()->routeIs('contact')],
        ['label' => 'Login', 'url' => route('login'), 'active' => request()->routeIs('login')],
    ];
@endphp

<header class="sticky top-0 z-50">
    {{-- Desktop top bar --}}
    <div class="hidden border-b border-navy-800/50 bg-navy-950 text-sm text-navy-300 lg:block">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-2.5 lg:px-8">
            <div class="flex min-w-0 items-center gap-6">
                <a href="tel:{{ preg_replace('/\s+/', '', $institute['phone']) }}" class="inline-flex shrink-0 items-center gap-2 transition hover:text-brand-400">
                    <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                    </svg>
                    {{ $institute['phone'] }}
                </a>
                <span class="text-navy-600">|</span>
                <a href="mailto:{{ $institute['email'] }}" class="truncate transition hover:text-brand-400">{{ $institute['email'] }}</a>
            </div>
            <p class="shrink-0">{{ $institute['hours'] }}</p>
        </div>
    </div>

    {{-- Main nav --}}
    <div class="border-b border-navy-100/80 bg-white/95 shadow-sm shadow-navy-900/5 backdrop-blur-md">
        <div class="relative mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8 lg:py-4">
            <a href="{{ route('home') }}" class="group flex min-w-0 flex-1 items-center lg:flex-none">
                @if (! empty($institute['logo_url']))
                    <div
                        class="flex h-12 shrink-0 items-center justify-start sm:h-14"
                        style="width: min(100%, {{ \App\Support\SiteLogo::DISPLAY_MAX_WIDTH }}px); aspect-ratio: {{ \App\Support\SiteLogo::ASPECT_WIDTH }} / {{ \App\Support\SiteLogo::ASPECT_HEIGHT }};"
                    >
                        <img
                            src="{{ $institute['logo_url'] }}"
                            alt="{{ $institute['name'] }}"
                            class="h-full w-full object-contain object-left"
                        >
                    </div>
                @else
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-xs font-bold text-white shadow-lg shadow-brand-500/30 sm:h-12 sm:w-12 sm:text-sm">
                        {{ $initials }}
                    </div>
                    <div class="min-w-0 leading-tight">
                        <div class="truncate font-display text-base font-bold text-navy-900 sm:text-xl">{{ $institute['name'] }}</div>
                        <div class="hidden truncate text-xs font-medium text-navy-500 sm:block">{{ $institute['tagline'] }}</div>
                    </div>
                @endif
            </a>

            <nav class="hidden items-center gap-1 lg:flex">
                @foreach ($navLinks as $link)
                    <a
                        href="{{ $link['url'] }}"
                        @class([
                            'rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                            'bg-brand-50 text-brand-800' => $link['active'],
                            'text-navy-600 hover:bg-navy-50 hover:text-navy-900' => ! $link['active'],
                        ])
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
                <a
                    href="{{ route('contact') }}"
                    class="ml-3 inline-flex min-h-[44px] items-center rounded-xl bg-gradient-to-r from-brand-500 to-brand-600 px-6 py-2.5 text-sm font-bold text-navy-950 shadow-md shadow-brand-500/25 transition hover:from-brand-400 hover:to-brand-500"
                >
                    Enquire Now
                </a>
            </nav>

            {{-- Mobile menu --}}
            <details class="mobile-nav group relative shrink-0 lg:hidden">
                <summary class="flex min-h-[44px] min-w-[44px] cursor-pointer list-none items-center justify-center rounded-xl border border-navy-200 bg-navy-50 text-navy-800 touch-manipulation">
                    <svg class="h-6 w-6 group-open:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg class="hidden h-6 w-6 group-open:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </summary>
                <div class="absolute right-0 top-[calc(100%+0.5rem)] z-50 w-[min(100vw-2rem,20rem)] overflow-hidden rounded-2xl border border-navy-100 bg-white shadow-2xl shadow-navy-900/15">
                    <nav class="flex flex-col p-2">
                        @foreach ($navLinks as $link)
                            <a
                                href="{{ $link['url'] }}"
                                @class([
                                    'flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold touch-manipulation',
                                    'bg-brand-50 text-brand-800' => $link['active'],
                                    'text-navy-700 active:bg-navy-50' => ! $link['active'],
                                ])
                            >
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                        <a
                            href="{{ route('contact') }}"
                            class="mt-2 flex min-h-[48px] items-center justify-center rounded-xl bg-brand-500 px-4 text-base font-bold text-navy-950 touch-manipulation active:bg-brand-600"
                        >
                            Enquire Now
                        </a>
                    </nav>
                </div>
            </details>
        </div>
    </div>
</header>
