@extends('layouts.portal')

@section('title', 'Topics')
@section('heading', 'Topics')
@section('subheading', 'What this class will cover. Time is not shown here.')

@section('content')
    <div class="space-y-4">
        @forelse ($groups as $group)
            <section class="rounded-2xl border border-navy-100 bg-white p-4 shadow-sm">
                <p class="font-display text-lg font-bold text-navy-900">{{ $group['subject'] }}</p>
                <p class="mt-0.5 text-sm text-navy-500">Faculty: {{ $group['faculty'] }}</p>

                <div class="mt-4 space-y-4">
                    @foreach ($group['chapters'] as $chapter)
                        <div>
                            <p class="text-sm font-semibold text-navy-800">{{ $chapter['name'] }}</p>
                            <ul class="mt-2 divide-y divide-navy-100">
                                @foreach ($chapter['topics'] as $topic)
                                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
                                        <span class="text-sm text-navy-900">{{ $topic['name'] }}</span>
                                        <span class="text-xs text-navy-500">
                                            DPP {{ $topic['dpp_count'] }}
                                            · Quiz/PYQs {{ $topic['quiz_count'] }}
                                            · Test {{ $topic['test_count'] }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-navy-200 bg-white px-6 py-10 text-center">
                <p class="text-sm font-medium text-navy-700">No topics yet</p>
                <p class="mt-1 text-sm text-navy-500">Topics show here after the academic head finalizes the section plan.</p>
            </div>
        @endforelse
    </div>
@endsection
