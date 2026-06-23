@php
    /** @var array<string, string|null> $snapshot */
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Institute snapshot</p>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-400">Name</p>
            <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $snapshot['name'] }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-400">ID prefix</p>
            <p class="mt-1 font-mono text-lg font-semibold text-gray-950 dark:text-white">{{ $snapshot['prefix'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Edit under Website → Site Content</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-400">Current session</p>
            <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $snapshot['session'] ?? 'Not set' }}</p>
        </div>
    </div>
</div>
