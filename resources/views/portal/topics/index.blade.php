@extends('layouts.portal')

@section('title', 'Topics')
@section('heading', 'Topics')
@section('subheading', 'Chapters and topics for this class.')

@section('content')
    <div class="space-y-5">
        @forelse ($groups as $group)
            @php
                $chapterCount = count($group['chapters']);
                $topicCount = 0;

                foreach ($group['chapters'] as $chapter) {
                    $topicCount += count($chapter['topics']);
                }
            @endphp

            <article class="overflow-hidden rounded-2xl border border-navy-100 bg-white shadow-sm">
                <header class="bg-navy-900 px-4 py-4 text-white sm:px-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-brand-300">Subject</p>
                            <h2 class="mt-1 font-display text-xl font-bold leading-tight">{{ $group['subject'] }}</h2>
                            <p class="mt-1 text-sm text-navy-200">Faculty: {{ $group['faculty'] }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-display text-2xl font-bold leading-none text-brand-300">{{ $chapterCount }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-navy-300">{{ $chapterCount === 1 ? 'chapter' : 'chapters' }}</p>
                            <p class="mt-2 text-xs text-navy-300">{{ $topicCount }} {{ $topicCount === 1 ? 'topic' : 'topics' }}</p>
                        </div>
                    </div>
                </header>

                <div class="divide-y divide-navy-100">
                    @foreach ($group['chapters'] as $chapterIndex => $chapter)
                        <section class="px-4 py-4 sm:px-5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-navy-900">{{ $chapterIndex + 1 }}</span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-navy-900 sm:text-base">{{ $chapter['name'] }}</h3>
                                    <p class="text-xs text-navy-500">{{ count($chapter['topics']) }} {{ count($chapter['topics']) === 1 ? 'topic' : 'topics' }}</p>
                                </div>
                            </div>

                            <ol class="mt-3 space-y-2 sm:pl-11">
                                @foreach ($chapter['topics'] as $topic)
                                    <li class="flex flex-col gap-2 rounded-xl bg-navy-50 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="min-w-0 text-sm leading-snug text-navy-800">{{ $topic['name'] }}</p>
                                        @if ($topic['dpp_count'] > 0 || $topic['quiz_count'] > 0 || $topic['test_count'] > 0)
                                            <div class="flex shrink-0 flex-wrap gap-1.5">
                                                @if ($topic['dpp_count'] > 0)
                                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">DPP {{ $topic['dpp_count'] }}</span>
                                                @endif
                                                @if ($topic['quiz_count'] > 0)
                                                    <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">Quiz/PYQs {{ $topic['quiz_count'] }}</span>
                                                @endif
                                                @if ($topic['test_count'] > 0)
                                                    <span class="rounded-full bg-navy-900 px-2 py-0.5 text-[11px] font-semibold text-white">Test {{ $topic['test_count'] }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-navy-200 bg-white px-6 py-10 text-center">
                <p class="text-sm font-medium text-navy-700">No topics yet</p>
                <p class="mt-1 text-sm text-navy-500">Topics show here after the academic head finalizes the section plan.</p>
            </div>
        @endforelse
    </div>
@endsection
