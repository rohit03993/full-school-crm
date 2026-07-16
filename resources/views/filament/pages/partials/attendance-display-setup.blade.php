@php
    /** @var bool $enabled */
    /** @var string|null $displayUrl */
    /** @var string $attendanceUrl */
    /** @var string|null $hint */
@endphp

<div class="space-y-6">
    @if (filled($hint))
        <div class="rounded-2xl border border-primary-500/20 bg-primary-500/5 px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
            {{ $hint }}
        </div>
    @endif

    <div class="fi-section rounded-2xl p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Status</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    @if ($enabled)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Enabled
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">Disabled</span>
                    @endif
                </p>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                    Read-only screen for reception. Works with biometric punches and manual IN/OUT from
                    <a href="{{ $attendanceUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Academics → Attendance (Live)</a>.
                    Does not change how attendance is saved.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($enabled)
                    <button
                        type="button"
                        wire:click="disableDisplay"
                        wire:loading.attr="disabled"
                        class="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-200 disabled:opacity-60 dark:bg-white/10 dark:text-gray-100"
                    >
                        Disable
                    </button>
                @else
                    <button
                        type="button"
                        wire:click="enableDisplay"
                        wire:loading.attr="disabled"
                        class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-60"
                    >
                        Enable display
                    </button>
                @endif

                <button
                    type="button"
                    wire:click="regenerateDisplayToken"
                    wire:loading.attr="disabled"
                    wire:confirm="This will invalidate the current TV link. Continue?"
                    class="rounded-xl bg-amber-500/15 px-4 py-2.5 text-sm font-semibold text-amber-900 ring-1 ring-amber-500/25 hover:bg-amber-500/20 disabled:opacity-60 dark:text-amber-200"
                >
                    {{ $enabled ? 'Regenerate link' : 'Generate link' }}
                </button>
            </div>
        </div>

        @if (filled($displayUrl))
            <div class="mt-5 rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Display URL (open on TV / tablet)</p>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <code class="block flex-1 break-all rounded-lg bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 dark:bg-gray-950 dark:text-gray-100 dark:ring-white/10">{{ $displayUrl }}</code>
                    <button
                        type="button"
                        x-data
                        x-on:click="navigator.clipboard.writeText(@js($displayUrl))"
                        class="shrink-0 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900"
                    >
                        Copy link
                    </button>
                    <a
                        href="{{ $displayUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="shrink-0 rounded-xl bg-primary-500/10 px-4 py-2.5 text-center text-sm font-semibold text-primary-700 ring-1 ring-primary-500/20 hover:bg-primary-500/15 dark:text-primary-300"
                    >
                        Open preview
                    </a>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Keep this link private. Anyone with the link can see names and photos when punches happen.</p>
            </div>
        @endif
    </div>

    <div class="fi-section rounded-2xl p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">Setup checklist</p>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-300">
            <li>Enable display and copy the URL above.</li>
            <li>Open it in Chrome/Edge on the reception PC → press F11 for full screen.</li>
            <li>Ensure students have a photo uploaded in their profile (Admission documents).</li>
            <li>Test with one manual IN from Attendance → Live, or one biometric punch.</li>
        </ol>
    </div>
</div>
