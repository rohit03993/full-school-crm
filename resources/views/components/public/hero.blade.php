@props(['courses'])

@php
    $images = $institute['images']['hero'];
    $heroStats = $institute['hero_stats'] ?? [];
@endphp

<section class="relative min-h-[min(100svh,900px)] overflow-hidden bg-navy-950 text-white sm:min-h-[88vh]">
    <div class="absolute inset-0">
        <img
            src="{{ $images['main'] }}"
            alt="Students learning on campus"
            class="h-full w-full object-cover object-center"
            fetchpriority="high"
        >
        <div class="absolute inset-0 bg-gradient-to-b from-navy-950/95 via-navy-950/85 to-navy-950/75 sm:bg-gradient-to-r sm:from-navy-950/95 sm:via-navy-950/80 sm:to-navy-950/40"></div>
    </div>

    <div class="relative mx-auto flex min-h-[min(100svh,900px)] max-w-7xl flex-col justify-center px-4 py-20 pt-8 sm:min-h-[88vh] sm:px-6 sm:py-24 lg:px-8">
        <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-16">
            <div class="max-w-xl lg:max-w-none">
                <p class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-full border border-brand-400/40 bg-brand-500/15 px-3 py-1.5 text-xs font-medium text-brand-200 backdrop-blur-sm sm:px-4 sm:text-sm">
                    <span class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-brand-400"></span>
                    <span class="break-words">Education Since {{ $institute['established'] }}</span>
                </p>
                <h1 class="mt-5 font-display text-[1.85rem] font-bold leading-[1.12] tracking-tight sm:mt-6 sm:text-5xl lg:text-6xl xl:text-7xl">
                    {{ $institute['hero']['title'] }}
                </h1>
                <p class="mt-4 text-base leading-relaxed text-navy-100 sm:mt-6 sm:text-lg lg:text-xl">
                    {{ $institute['hero']['subtitle'] }}
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:mt-10 sm:flex-row sm:gap-4">
                    <a
                        href="{{ route('courses') }}"
                        class="inline-flex min-h-[48px] w-full items-center justify-center rounded-xl bg-brand-500 px-6 py-3.5 text-base font-bold text-navy-950 shadow-xl shadow-brand-500/25 touch-manipulation active:bg-brand-600 sm:w-auto sm:px-8 sm:py-4"
                    >
                        Explore Programmes
                    </a>
                    <a
                        href="#gallery"
                        class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-6 py-3.5 text-base font-semibold text-white backdrop-blur-sm touch-manipulation active:bg-white/20 sm:w-auto sm:px-8 sm:py-4"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
                        </svg>
                        View Gallery
                    </a>
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex min-h-[48px] w-full items-center justify-center rounded-xl border border-white/25 bg-white/5 px-6 py-3.5 text-base font-semibold text-white backdrop-blur-sm touch-manipulation active:bg-white/15 sm:w-auto sm:px-8 sm:py-4"
                    >
                        Login
                    </a>
                </div>

                @if ($heroStats !== [])
                    <div class="mt-8 grid grid-cols-2 gap-4 border-t border-white/10 pt-6 sm:mt-12 sm:flex sm:flex-wrap sm:gap-6 sm:pt-8">
                        @foreach ($heroStats as $index => $stat)
                            <div @class(['col-span-2 sm:col-span-1' => $index === 2 && count($heroStats) === 3])>
                                <p class="font-display text-xl font-bold text-brand-400 sm:text-2xl">{{ $stat['title'] ?? '' }}</p>
                                <p class="text-xs text-navy-300 sm:text-sm">{{ $stat['subtitle'] ?? '' }}</p>
                            </div>
                            @if ($index < count($heroStats) - 1)
                                <div class="hidden h-10 w-px bg-white/15 sm:block"></div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Desktop collage --}}
            <div class="relative hidden lg:block">
                <div class="relative ml-auto w-full max-w-md">
                    <div class="overflow-hidden rounded-2xl border border-white/10 shadow-2xl shadow-black/40">
                        <img src="{{ $images['accent_one'] }}" alt="Classroom learning" class="aspect-[4/5] w-full object-cover" loading="lazy">
                    </div>
                    <div class="absolute -bottom-8 -left-12 w-48 overflow-hidden rounded-2xl border-4 border-navy-950 shadow-2xl">
                        <img src="{{ $images['accent_two'] }}" alt="Students studying" class="aspect-square w-full object-cover" loading="lazy">
                    </div>
                    <div class="absolute -right-4 top-8 rounded-2xl border border-brand-400/30 bg-navy-900/90 px-5 py-4 backdrop-blur-md">
                        <p class="text-xs font-medium uppercase tracking-wider text-brand-300">Now enrolling</p>
                        <p class="mt-1 font-display text-lg font-semibold">{{ $institute['home']['courses_eyebrow'] ?? 'Our Programmes' }}</p>
                        <p class="text-sm text-navy-300">{{ $courses->count() }} programmes open</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile image strip --}}
        <div class="-mx-4 mt-8 flex gap-3 overflow-x-auto px-4 pb-2 snap-x snap-mandatory scrollbar-none lg:hidden">
            @foreach ([$images['accent_one'], $images['accent_two'], $images['about']] as $img)
                <div class="h-36 w-52 shrink-0 snap-start overflow-hidden rounded-xl border border-white/15 shadow-lg sm:h-40 sm:w-60">
                    <img src="{{ $img }}" alt="Campus life" class="h-full w-full object-cover" loading="lazy">
                </div>
            @endforeach
        </div>
    </div>
</section>
