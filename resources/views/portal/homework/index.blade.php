@extends('layouts.portal')

@section('title', 'Homework')
@section('heading', 'Homework')
@section('subheading', $student->activeEnrollment?->enrollment_number
    ? \App\Support\StudentLabels::rollNumberLabel().' · '.$student->activeEnrollment->enrollment_number
    : $student->mobile)

@section('content')
    <div class="space-y-3">
        @forelse ($assignments as $assignment)
            @php
                $viewed = $assignment->views->isNotEmpty();
            @endphp
            <a href="{{ route('portal.homework.show', $assignment) }}"
               class="touch-manipulation block rounded-2xl border border-navy-100 bg-white p-4 shadow-sm transition active:scale-[0.99] hover:border-brand-300 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-base font-bold text-navy-900 sm:text-lg">{{ $assignment->title }}</p>
                        <p class="mt-0.5 text-sm text-navy-500">{{ $assignment->batch?->name }}</p>
                        <p class="mt-2 line-clamp-2 text-sm text-navy-700">{{ $assignment->description }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $viewed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">
                        {{ $viewed ? 'Viewed' : 'New' }}
                    </span>
                </div>
                <p class="mt-2.5 text-xs text-navy-400">Published {{ $assignment->published_at?->format('d M Y') }}</p>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-navy-200 bg-white px-6 py-10 text-center">
                <p class="text-sm font-medium text-navy-700">No homework yet</p>
                <p class="mt-1 text-sm text-navy-500">Assignments for your batch will show up here.</p>
            </div>
        @endforelse
    </div>
@endsection
