@php
    /** @var array{ready: bool, warning: ?string, template_name: ?string, student_name: ?string, mobile: ?string, body: ?string, param_count: int, selected_count: int} $preview */
@endphp

<div class="space-y-3">
    @if (filled($preview['warning'] ?? null))
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">
            {{ $preview['warning'] }}
        </p>
    @endif

    @if (filled($preview['body'] ?? null))
        <div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
            @if ($preview['ready'] ?? false)
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                    Preview for
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $preview['student_name'] }}</span>
                    @if (filled($preview['mobile'] ?? null))
                        · {{ $preview['mobile'] }}
                    @endif
                    @if (($preview['selected_count'] ?? 0) > 1)
                        · other selected parents get their own amount / due date
                    @endif
                </p>
            @elseif (filled($preview['template_name'] ?? null))
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                    Template: {{ $preview['template_name'] }}
                </p>
            @endif
            <pre class="whitespace-pre-wrap font-sans text-sm leading-relaxed text-gray-900 dark:text-gray-100">{{ $preview['body'] }}</pre>
        </div>
    @elseif (! filled($preview['warning'] ?? null))
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Fill amount and due date on selected students to see the message preview.
        </p>
    @endif
</div>
