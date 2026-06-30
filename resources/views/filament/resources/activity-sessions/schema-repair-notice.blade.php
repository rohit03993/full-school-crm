<div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-500/30 dark:bg-amber-500/10">
    <h3 class="text-base font-bold text-amber-950 dark:text-amber-100">Database upgrade required</h3>
    <p class="mt-2 text-sm text-amber-900 dark:text-amber-200">
        Tests &amp; Exams needs the new activity tables. This usually happens once after pulling the latest CRM code on the server.
    </p>
    <p class="mt-3 text-sm font-mono text-amber-950 dark:text-amber-100">
        php artisan crm:repair-schema --force
    </p>
    <p class="mt-3 text-xs text-amber-800 dark:text-amber-300">
        Run that command in SSH from your site folder, then refresh this page. If it still fails, check <code>storage/logs/laravel.log</code>.
    </p>
</div>
