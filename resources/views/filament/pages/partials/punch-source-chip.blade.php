@php
    $isManual = (bool) ($isManual ?? false);
    $isAuto = (bool) ($isAuto ?? false);
    $device = $device ?? null;
    $staffName = $staffName ?? null;

    if ($isAuto) {
        $label = 'Auto OUT';
        $tone = 'auto';
    } elseif ($isManual) {
        $label = \App\Support\AttendanceSourceLabel::manualMarked($staffName);
        $tone = 'manual';
    } elseif (filled($device) && strcasecmp((string) $device, 'Manual') !== 0 && strcasecmp((string) $device, 'Auto') !== 0) {
        $label = (string) $device;
        $tone = 'machine';
    } else {
        $label = 'Biometric';
        $tone = 'machine';
    }
@endphp

<span @class([
    'mt-1.5 inline-flex max-w-full items-start rounded-md px-1.5 py-1 text-[10px] leading-snug',
    'bg-violet-500/10 font-medium text-violet-800 dark:text-violet-200' => $tone === 'manual',
    'bg-sky-500/10 font-medium text-sky-800 dark:text-sky-200' => $tone === 'machine',
    'bg-gray-100 font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300' => $tone === 'auto',
])>
    <span class="break-words">{{ $label }}</span>
</span>
