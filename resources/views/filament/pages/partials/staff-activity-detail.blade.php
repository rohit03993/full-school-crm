<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ $backUrl }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
            ← Back to activity
        </a>
        <div class="crm-seg" role="group" aria-label="Activity range">
            @foreach ($ranges as $option)
                <button
                    type="button"
                    wire:click="setRange('{{ $option->value }}')"
                    @class(['crm-seg__btn', 'crm-seg__btn--active' => $range === $option->value])
                >
                    {{ $option->label() }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/[0.06] dark:bg-gray-900 dark:ring-white/10">
        @forelse ($rows as $row)
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-0 dark:border-white/10">
                <div class="min-w-0">
                    @if ($row['url'])
                        <a href="{{ $row['url'] }}" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ $row['title'] }}</a>
                    @else
                        <p class="font-medium text-gray-950 dark:text-white">{{ $row['title'] }}</p>
                    @endif
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $row['detail'] }}</p>
                </div>
                <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $row['when'] }}</p>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Nothing in this range.</p>
        @endforelse
    </div>

    @if ($rows->hasPages())
        <div>{{ $rows->links() }}</div>
    @endif
</div>
