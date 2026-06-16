@extends('layouts.public')

@section('content')
    <section class="border-b border-navy-100 bg-navy-950 py-16 text-white sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-400">Programmes</p>
            <h1 class="mt-3 font-display text-4xl font-bold sm:text-5xl">Our Courses</h1>
            <p class="mt-4 max-w-2xl text-lg text-navy-200">
                Explore BSc and Diploma programmes in Hotel Management. Each course can be customised — contact us for details and fees.
            </p>
        </div>
    </section>

    <section class="py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @forelse ($courseGroups as $groupName => $courses)
                <div @class(['mb-16 last:mb-0' => true])>
                    <div class="mb-6 flex flex-col gap-2 sm:mb-8 sm:flex-row sm:items-center sm:gap-4">
                        <h2 class="font-display text-xl font-bold text-navy-900 sm:text-2xl">{{ $groupName }}</h2>
                        <div class="hidden h-px flex-1 bg-navy-100 sm:block"></div>
                        <span class="text-sm font-medium text-navy-400">{{ $courses->count() }} programme{{ $courses->count() === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3">
                        @foreach ($courses as $course)
                            <x-public.course-card :course="$course" />
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-navy-200 bg-navy-50 p-16 text-center">
                    <h2 class="font-display text-xl font-semibold text-navy-800">No courses listed yet</h2>
                    <p class="mt-2 text-navy-600">Courses added in the admin panel will appear here automatically.</p>
                    <a href="{{ route('contact') }}" class="mt-6 inline-flex rounded-xl bg-navy-900 px-6 py-3 text-sm font-semibold text-white">
                        Contact us for programme details
                    </a>
                </div>
            @endforelse
        </div>
    </section>

    <section class="border-t border-navy-100 bg-brand-50 py-12">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h2 class="font-display text-2xl font-bold text-navy-900">Need a custom programme?</h2>
            <p class="mt-3 text-navy-600">
                We can create tailored short-term and certificate courses. Speak with our team to design a programme for your needs.
            </p>
            <a href="{{ route('contact') }}" class="mt-6 inline-flex rounded-xl bg-brand-500 px-8 py-3 font-semibold text-navy-950 transition hover:bg-brand-400">
                Get in touch
            </a>
        </div>
    </section>
@endsection
