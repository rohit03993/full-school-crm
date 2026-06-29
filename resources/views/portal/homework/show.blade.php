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

        @if ($homework->hasFile() && $homework->isPreviewable())
            @php
                $viewUrl = $homework->portalViewUrl();
                $isPdf = $homework->content_type === \App\Enums\HomeworkContentType::Pdf;
            @endphp
            <section class="portal-card p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-navy-900">Attachment ({{ $homework->content_type->label() }})</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $viewUrl }}"
                           target="_blank"
                           rel="noopener"
                           class="touch-manipulation inline-flex items-center rounded-xl border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 shadow-sm hover:bg-navy-50">
                            Open
                        </a>
                        <a href="{{ route('portal.homework.download', $homework) }}"
                           class="touch-manipulation inline-flex items-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-navy-950 shadow-sm hover:bg-brand-400">
                            Download
                        </a>
                    </div>
                </div>

                @if ($isPdf)
                    <iframe
                        src="{{ $viewUrl }}"
                        class="mt-4 h-[55vh] w-full rounded-xl border border-navy-100 sm:h-[65vh]"
                        title="{{ $homework->title }}"
                    ></iframe>
                    <p class="mt-3 text-center text-xs text-navy-500 sm:hidden">
                        PDF not showing?
                        <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="font-semibold text-brand-700 underline underline-offset-2">Tap here to open it</a>
                    </p>
                @else
                    <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="mt-4 block">
                        <img
                            src="{{ $viewUrl }}"
                            alt="{{ $homework->title }}"
                            class="max-h-[60vh] w-full rounded-xl object-contain sm:max-h-[70vh]"
                        >
                    </a>
                @endif
            </section>
        @elseif ($homework->hasFile())
            <section class="portal-card p-4 sm:p-5">
                <a href="{{ route('portal.homework.download', $homework) }}"
                   class="touch-manipulation inline-flex items-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-navy-950 shadow-sm hover:bg-brand-400">
                    Download attachment
                </a>
            </section>
        @endif
    </article>
@endsection
