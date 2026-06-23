@props(['course'])

@php
    $feeLabel = (float) $course->fee > 0
        ? $course->formatted_fee
        : 'Contact for fees';
@endphp

<article {{ $attributes->merge(['class' => 'group flex h-full flex-col overflow-hidden rounded-2xl border border-navy-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-lg hover:shadow-brand-500/10']) }}>
    <div class="border-b border-navy-50 bg-gradient-to-br from-navy-50 to-brand-50/40 px-6 py-5">
        <div class="flex items-start justify-between gap-3">
            <span class="rounded-lg bg-navy-900 px-2.5 py-1 font-mono text-xs font-medium text-brand-300">
                {{ $course->code }}
            </span>
        </div>
        <h3 class="mt-4 break-words font-display text-lg font-semibold leading-snug text-navy-900 sm:text-xl group-hover:text-brand-800">
            {{ $course->name }}
        </h3>
    </div>

    <div class="flex flex-1 flex-col px-6 py-5">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-navy-400">Duration</dt>
                <dd class="mt-1 font-semibold text-navy-800">{{ $course->duration_label }}</dd>
            </div>
            <div>
                <dt class="text-navy-400">Fee</dt>
                <dd class="mt-1 font-semibold text-brand-700">{{ $feeLabel }}</dd>
            </div>
        </dl>

        @if ($course->description)
            <p class="mt-4 flex-1 text-sm leading-relaxed text-navy-600 line-clamp-3">
                {{ $course->description }}
            </p>
        @endif

        <a
            href="{{ route('contact') }}"
            class="mt-6 inline-flex min-h-[48px] w-full items-center justify-center rounded-xl border border-navy-200 px-4 py-3 text-sm font-semibold text-navy-800 touch-manipulation transition active:bg-navy-50 group-hover:border-brand-400 group-hover:bg-brand-50 group-hover:text-brand-800"
        >
            Enquire about this course
        </a>
    </div>
</article>
