@php
    use App\Enums\OccasionWishType;
    use App\Services\OccasionWishService;
    use App\Support\InstituteSettings;

    $service = app(OccasionWishService::class);
    $birthdayToday = $service->occursOn($record->date_of_birth, now());
    $message = null;
    $line = 'Today is a special day.';

    if ($birthdayToday) {
        $on = now()->toDateString();
        $states = $service->messageStates([[
            'occasion' => OccasionWishType::StudentBirthday->value,
            'recipient_key' => 'student:'.$record->id,
            'on' => $on,
            'mobile' => (string) ($record->mobile ?? ''),
        ]]);
        $message = $states[$on.'|student:'.$record->id.'|'.OccasionWishType::StudentBirthday->value] ?? null;
        $school = InstituteSettings::brandName();
        $line = filled($school)
            ? 'Today is a special day at '.$school.'.'
            : 'Today is a special day.';
    }
@endphp

@if ($birthdayToday)
    <section class="mb-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 dark:border-rose-500/20 dark:bg-rose-500/10">
        <p class="text-base font-bold text-rose-800 dark:text-rose-200">Happy birthday, {{ $record->name }}</p>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $line }}</p>
        @if (is_array($message))
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-900 dark:text-gray-200">{{ $message['label'] }}</span>
                @if ($message['can_send'])
                    <button
                        type="button"
                        wire:click="sendBirthdayWish"
                        wire:loading.attr="disabled"
                        class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-500"
                    >
                        Send message
                    </button>
                @endif
            </div>
        @endif
    </section>
@endif
