@php
    $gallery = $institute['images']['gallery'] ?? [];
@endphp

<section id="gallery" class="scroll-mt-36 bg-white py-14 sm:scroll-mt-28 sm:py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl text-center">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">Campus & Learning</p>
            <h2 class="mt-3 font-display text-2xl font-bold text-navy-900 sm:text-4xl lg:text-5xl">
                Life at {{ $institute['name'] }}
            </h2>
            <p class="mt-4 text-base leading-relaxed text-navy-600 sm:text-lg">
                A vibrant campus with classrooms, labs, and coaching centres — built for academic growth and student success.
            </p>
        </div>

        <div class="mt-10 grid grid-cols-1 gap-3 sm:mt-14 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4 lg:auto-rows-[240px]">
            @foreach ($gallery as $item)
                <figure @class([
                    'group relative min-h-[220px] overflow-hidden rounded-2xl bg-navy-100 sm:min-h-[200px]',
                    $item['span'] ?? '',
                ])>
                    <img
                        src="{{ $item['src'] }}"
                        alt="{{ $item['alt'] }}"
                        class="h-full w-full object-cover transition duration-700 group-active:scale-105 lg:group-hover:scale-110"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-navy-950/90 via-navy-950/25 to-transparent"></div>
                    <figcaption class="absolute inset-x-0 bottom-0 p-4 sm:p-5">
                        <p class="text-sm font-semibold text-white sm:text-base">
                            {{ $item['caption'] }}
                        </p>
                    </figcaption>
                </figure>
            @endforeach
        </div>

        <div class="mt-10 text-center sm:mt-12">
            <a
                href="{{ route('contact') }}"
                class="inline-flex min-h-[48px] w-full max-w-sm items-center justify-center gap-2 rounded-xl bg-navy-900 px-8 py-3.5 text-sm font-semibold text-white touch-manipulation active:bg-navy-800 sm:w-auto sm:py-4"
            >
                Schedule a campus visit
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </div>
</section>
