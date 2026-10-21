@php
    use App\Enums\OccasionWishType;
    use App\Services\OccasionWishService;
    use App\Support\InstituteSettings;

    $service = app(OccasionWishService::class);
    $birthdayToday = $service->occursOn($record->date_of_birth, now());
    $message = null;
    $line = 'Wishing you a very happy birthday.';

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
            ? $school.' wishes you a very happy birthday.'
            : 'Wishing you a very happy birthday.';
    }
@endphp

@if ($birthdayToday)
    <section class="mb-3" style="position: relative; overflow: hidden; border-radius: 1rem; border: 1px solid #fda4af; background: linear-gradient(135deg, #fff1f2 0%, #fff7ed 48%, #fef3c7 100%); padding: 1.1rem 1.25rem;">
        <span style="position: absolute; top: -1.2rem; right: 4.5rem; width: 4.5rem; height: 4.5rem; border-radius: 999px; background: #fecdd3; opacity: 0.7;"></span>
        <span style="position: absolute; bottom: -1.6rem; right: -0.8rem; width: 5rem; height: 5rem; border-radius: 999px; background: #fde68a; opacity: 0.65;"></span>
        <div style="position: relative; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.9rem; min-width: 0;">
                <div style="display: flex; height: 3.6rem; width: 3.6rem; flex: none; align-items: center; justify-content: center; border-radius: 999px; background: #fff; box-shadow: 0 8px 18px rgba(225, 29, 72, 0.12);">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 13.5h16v2.2a3.3 3.3 0 0 1-3.3 3.3H7.3A3.3 3.3 0 0 1 4 15.7v-2.2Z" fill="#fb7185"/>
                        <path d="M4 11.2c1.2 1.1 2.4.2 3.6-1 1.2 1.2 2.4 2.1 3.6 1 1.2 1.1 2.4.2 3.6-1 1.2 1.2 2.4 2.1 3.6 1v2.3H4v-2.3Z" fill="#fda4af"/>
                        <path d="M12 4.2v4.2" stroke="#e11d48" stroke-width="1.6" stroke-linecap="round"/>
                        <path d="M12 3.2c.7.5 1.1 1 .8 1.6-.5-.1-1-.5-1.3-.9.2-.3.4-.5.5-.7Z" fill="#f59e0b"/>
                    </svg>
                </div>
                <div style="min-width: 0;">
                    <p style="margin: 0; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #e11d48;">Birthday today</p>
                    <p style="margin: 0.15rem 0 0; font-size: 1.25rem; font-weight: 800; line-height: 1.25; color: #881337;">Happy birthday, {{ $record->name }}</p>
                    <p style="margin: 0.3rem 0 0; font-size: 0.92rem; color: #57534e;">{{ $line }}</p>
                </div>
            </div>
            @if (is_array($message))
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                    <span style="border-radius: 999px; background: #fff; padding: 0.35rem 0.7rem; font-size: 0.75rem; font-weight: 700; color: #44403c;">{{ $message['label'] }}</span>
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
        </div>
    </section>
@endif
