@php
    $statusLabel = match (true) {
        ! $signatureValid => 'Tampered or invalid signature',
        ! $licenseActive => 'Expired',
        default => 'Active',
    };
@endphp

<div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">License status</p>
            <p class="text-lg font-semibold">{{ $statusLabel }}</p>
        </div>
        @if ($daysRemaining !== null)
            <div class="text-right">
                <p class="text-sm text-gray-500 dark:text-gray-400">Days remaining</p>
                <p class="text-lg font-semibold @if($daysRemaining < 30) text-warning-600 @endif">{{ $daysRemaining }}</p>
            </div>
        @endif
    </div>
    @if (! $signatureValid)
        <p class="mt-3 text-sm text-danger-600">
            The stored license was changed without authorization. School admin is locked until you save a valid license here.
        </p>
    @endif
</div>
