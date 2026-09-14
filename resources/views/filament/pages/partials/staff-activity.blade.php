<div class="mx-auto max-w-5xl space-y-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/[0.06] dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $periodLabel }}</p>
                @if ($viewingOther)
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $subjectName }} — only their own actions</p>
                @endif
            </div>
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
    </div>

    @if ($tiles === [])
        <p class="rounded-xl bg-white px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-950/[0.06] dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            No tracked actions for this login.
        </p>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tiles as $tile)
                <a
                    href="{{ $tile['url'] }}"
                    class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/[0.06] transition hover:ring-primary-500/40 dark:bg-gray-900 dark:ring-white/10"
                >
                    <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $tile['value'] }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $tile['label'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $tile['meta'] }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
