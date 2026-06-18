@php
    /** @var array<int, array{label: string, description: string, url: string, icon: string}> $links */
@endphp

<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Configuration shortcuts</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage sessions, programmes, branding, and website content.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($links as $link)
            <a
                href="{{ $link['url'] }}"
                class="group flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600"
            >
                <div class="flex items-start gap-3">
                    <span class="inline-flex rounded-lg bg-primary-50 p-2 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                            {{ $link['label'] }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $link['description'] }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</div>
