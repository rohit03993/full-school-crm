@props([
    'mobile' => null,
])

@php
    $canView = \App\Support\CrmAccess::canViewStudentMobile(auth()->user());
    $label = \App\Support\CrmAccess::studentMobileLabel(auth()->user(), $mobile);
@endphp

<span {{ $attributes->class([
    'crm-student-mobile',
    'text-gray-400 dark:text-gray-500' => ! $canView || ! filled($mobile),
    'font-mono' => $canView && filled($mobile),
]) }}>{{ $label }}</span>
