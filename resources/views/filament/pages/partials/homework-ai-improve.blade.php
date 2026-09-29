@php
    $hasSuggestion = $suggestedTitle !== null || $suggestedDescription !== null;
@endphp

<div class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
        AI improvements used today: {{ (int) $usedToday }} / {{ (int) $dailyLimit }}
    </p>

    @if (filled($message))
        <p class="text-sm text-amber-700 dark:text-amber-300">{{ $message }}</p>
    @endif

    @if ($hasSuggestion)
        <div class="space-y-1 rounded-lg bg-white p-3 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Original teacher input</p>
            <p><span class="font-semibold">Title:</span> {{ $originalTitle !== '' ? $originalTitle : '—' }}</p>
            <p class="whitespace-pre-wrap"><span class="font-semibold">Details:</span> {{ $originalDescription !== '' ? $originalDescription : '—' }}</p>
        </div>

        <div class="space-y-2">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">AI suggested homework</p>

            @if ($editing)
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300" for="homework-ai-title">Title</label>
                <input
                    id="homework-ai-title"
                    type="text"
                    maxlength="255"
                    wire:model="homeworkAiSuggestedTitle"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                />
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300" for="homework-ai-details">Details</label>
                <textarea
                    id="homework-ai-details"
                    rows="4"
                    maxlength="4000"
                    wire:model="homeworkAiSuggestedDescription"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                ></textarea>
            @else
                <div class="rounded-lg bg-white p-3 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-100">
                    <p><span class="font-semibold">Title:</span> {{ $suggestedTitle }}</p>
                    <p class="mt-2 whitespace-pre-wrap">{{ $suggestedDescription }}</p>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="useHomeworkAiSuggestion"
                wire:loading.attr="disabled"
                wire:target="useHomeworkAiSuggestion,retryHomeworkAi,improveHomeworkWithAi"
                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:opacity-60"
            >
                Use This
            </button>
            <button
                type="button"
                wire:click="editHomeworkAiSuggestion"
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-white dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
            >
                Edit
            </button>
            <button
                type="button"
                wire:click="retryHomeworkAi"
                wire:loading.attr="disabled"
                wire:target="retryHomeworkAi,improveHomeworkWithAi"
                @disabled($tries >= $maxTries)
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-white disabled:opacity-60 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
            >
                <span wire:loading.remove wire:target="retryHomeworkAi">Try Again</span>
                <span wire:loading wire:target="retryHomeworkAi">Improving homework...</span>
            </button>
        </div>
    @else
        <button
            type="button"
            wire:click="improveHomeworkWithAi"
            wire:loading.attr="disabled"
            wire:target="improveHomeworkWithAi"
            class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="improveHomeworkWithAi">Improve with AI</span>
            <span wire:loading wire:target="improveHomeworkWithAi">Improving homework...</span>
        </button>
    @endif
</div>
