@if (\App\Support\DemoMode::enabled())
    <div class="fi-demo-mode-banner w-full border-b border-amber-300 bg-amber-50 px-4 py-2 text-center text-sm font-medium text-amber-950 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-100">
        {{ \App\Support\DemoMode::bannerMessage() }}
    </div>
@endif
