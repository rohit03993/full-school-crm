@extends('layouts.portal')

@section('title', $homework->title)
@section('heading', $homework->title)
@section('subheading', $homework->batch?->name.' · Published '.$homework->published_at?->format('d M Y'))

@section('content')
    <article class="space-y-4">
        <section class="portal-card p-4 sm:p-5">
            <div class="prose prose-sm max-w-none text-navy-800">
                {!! nl2br(e($homework->description)) !!}
            </div>
        </section>

        @if ($homework->hasFile())
            <section class="portal-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-navy-900">Attachment ({{ $homework->content_type->label() }})</p>
                    <a href="{{ route('portal.homework.download', $homework) }}"
                       class="touch-manipulation inline-flex items-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-navy-950 shadow-sm hover:bg-brand-400">
                        Download
                    </a>
                </div>

                @if ($homework->content_type === \App\Enums\HomeworkContentType::Image && $homework->fileUrl())
                    <img src="{{ $homework->fileUrl() }}" alt="{{ $homework->title }}" class="mt-4 max-h-[60vh] w-full rounded-xl object-contain sm:max-h-[70vh]">
                @elseif ($homework->content_type === \App\Enums\HomeworkContentType::Pdf && $homework->fileUrl())
                    <iframe src="{{ $homework->fileUrl() }}" class="mt-4 h-[55vh] w-full rounded-xl border border-navy-100 sm:h-[65vh]" title="{{ $homework->title }}"></iframe>
                @endif
            </section>
        @endif
    </article>
@endsection
