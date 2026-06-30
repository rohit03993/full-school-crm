<x-filament-panels::page>
    <div class="mx-auto max-w-2xl space-y-4 rounded-xl border border-warning-300 bg-warning-50 p-6 text-gray-900 dark:border-warning-700 dark:bg-warning-950/30 dark:text-gray-100">
        <h2 class="text-xl font-semibold">Software license expired</h2>
        <p>
            This installation is temporarily locked because the annual license has ended
            @if ($expiresAt)
                on <strong>{{ $expiresAt }}</strong>
            @endif
            .
        </p>
        <p>
            Please contact your software provider to renew. Day-to-day school data is safe; access will restore automatically after renewal.
        </p>
    </div>
</x-filament-panels::page>
