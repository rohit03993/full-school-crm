@php
    $searchedName = $searchedName ?? '';
@endphp

<div class="fi-student-search-empty rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-4 py-3 text-center dark:border-white/10 dark:bg-white/[0.02]">
    <p class="text-sm font-semibold text-gray-950 dark:text-white">No student found for “{{ $searchedName }}”</p>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Try mobile number, or check spelling.</p>
</div>
