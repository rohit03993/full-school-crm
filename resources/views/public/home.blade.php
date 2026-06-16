@extends('layouts.public')

@section('content')
@php
    $aboutImage = $institute['images']['hero']['about'] ?? null;
@endphp
    <x-public.hero :course-groups="$courseGroups" />

    {{-- Highlights --}}
    <section id="highlights" class="scroll-mt-36 border-b border-navy-100 bg-white sm:scroll-mt-28">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-px bg-navy-100 md:grid-cols-4">
            @foreach ($institute['highlights'] as $item)
                <div class="bg-white px-6 py-10 text-center sm:px-8 sm:py-12">
                    <div class="font-display text-3xl font-bold text-brand-600 sm:text-4xl">{{ $item['value'] }}</div>
                    <div class="mt-2 text-sm font-medium text-navy-600">{{ $item['label'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Gallery --}}
    <x-public.gallery />

    {{-- About --}}
    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                <div class="relative order-2 lg:order-1">
                    <div class="overflow-hidden rounded-3xl shadow-2xl shadow-navy-900/15">
                        <img
                            src="{{ $aboutImage }}"
                            alt="Hospitality training at Folks India"
                            class="aspect-[4/5] w-full object-cover"
                            loading="lazy"
                        >
                    </div>
                    <div class="absolute -bottom-4 right-4 rounded-2xl border border-navy-100 bg-white p-4 shadow-xl sm:-bottom-6 sm:-right-8 sm:p-6">
                        <p class="text-sm text-navy-500">Programmes offered</p>
                        <p class="font-display text-3xl font-bold text-navy-900">{{ $courseGroups->flatten()->count() }}+</p>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">About Us</p>
                    <h2 class="mt-3 font-display text-3xl font-bold text-navy-900 sm:text-4xl">
                        Training the next generation of hospitality leaders
                    </h2>
                    <p class="mt-6 text-lg leading-relaxed text-navy-600">
                        {{ $institute['about'] }}
                    </p>
                    <ul class="mt-8 space-y-4">
                        @foreach (['Hands-on kitchen & front office labs', 'Industry visits & practical training', 'BSc & Diploma programmes with flexible durations', 'Career guidance from enquiry to placement'] as $point)
                            <li class="flex items-start gap-3 text-navy-700">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('contact') }}" class="mt-10 inline-flex items-center gap-2 font-semibold text-brand-700 hover:text-brand-800">
                        Learn more about admissions
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Courses preview --}}
    <section id="courses" class="scroll-mt-36 bg-navy-50 py-14 sm:scroll-mt-28 sm:py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">Our Programmes</p>
                    <h2 class="mt-3 font-display text-3xl font-bold text-navy-900 sm:text-4xl">
                        Courses designed for real careers
                    </h2>
                    <p class="mt-4 max-w-2xl text-navy-600">
                        BSc and Diploma options in Hotel Management — choose the duration that fits your goals.
                    </p>
                </div>
                <a href="{{ route('courses') }}" class="shrink-0 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    View all courses &rarr;
                </a>
            </div>

            @forelse ($courseGroups as $groupName => $courses)
                <div class="mt-12">
                    <h3 class="mb-6 font-display text-xl font-semibold text-navy-800">{{ $groupName }}</h3>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
                        @foreach ($courses as $course)
                            <x-public.course-card :course="$course" />
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="mt-12 rounded-2xl border border-dashed border-navy-200 bg-white p-12 text-center">
                    <p class="text-navy-600">Courses will appear here once added in the admin panel.</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl">
                <img
                    src="{{ $institute['images']['hero']['main'] }}"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover"
                    loading="lazy"
                    aria-hidden="true"
                >
                <div class="absolute inset-0 bg-navy-950/85"></div>
                <div class="relative px-8 py-16 text-center text-white sm:px-16 sm:py-24">
                    <h2 class="font-display text-3xl font-bold sm:text-4xl lg:text-5xl">Ready to start your hospitality career?</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-navy-200">
                        Visit our campus, speak with our counsellors, or call us to learn more about admissions.
                    </p>
                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <a
                            href="tel:{{ preg_replace('/\s+/', '', $institute['phone']) }}"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-8 py-4 font-bold text-navy-950 transition hover:bg-brand-400"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                            {{ $institute['phone'] }}
                        </a>
                        <a
                            href="{{ route('contact') }}"
                            class="inline-flex items-center rounded-xl border border-white/30 bg-white/10 px-8 py-4 font-semibold backdrop-blur-sm transition hover:bg-white/20"
                        >
                            Visit & Contact
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
