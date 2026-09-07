<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Enums\WhatsAppContactKind;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use App\Support\WhatsAppInboxContact;
use Illuminate\Support\Collection;

class WhatsAppInboxContactResolver
{
    public function __construct(
        protected StudentWhatsAppThreadService $thread,
    ) {}

    public function resolve(string $phone): WhatsAppInboxContact
    {
        $normalized = $this->thread->normalizePhoneForStorage($phone);

        if ($normalized === '') {
            return WhatsAppInboxContact::unknown();
        }

        return $this->resolveMany([$normalized])->get($normalized) ?? WhatsAppInboxContact::unknown();
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<string, WhatsAppInboxContact> keyed by normalized 91… phone
     */
    public function resolveMany(array $phones): Collection
    {
        $normalizedPhones = collect($phones)
            ->map(fn (string $phone): string => $this->thread->normalizePhoneForStorage($phone))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedPhones->isEmpty()) {
            return collect();
        }

        $tenDigits = $normalizedPhones
            ->map(fn (string $phone): string => $this->tenDigit($phone))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $studentsByTen = $this->studentsByTenDigit($tenDigits);
        $staffByTen = $this->staffByTenDigit($tenDigits);

        return $normalizedPhones->mapWithKeys(function (string $phone) use ($studentsByTen, $staffByTen): array {
            $ten = $this->tenDigit($phone);
            $student = $ten !== '' ? ($studentsByTen[$ten] ?? null) : null;
            $staff = $ten !== '' ? ($staffByTen[$ten] ?? null) : null;

            return [$phone => $this->buildContact($student, $staff)];
        });
    }

    protected function buildContact(?Student $student, ?User $staff): WhatsAppInboxContact
    {
        if ($student === null && $staff === null) {
            return WhatsAppInboxContact::unknown();
        }

        $tags = [];
        $kind = WhatsAppContactKind::Unknown;
        $displayName = 'Unknown contact';

        if ($staff !== null) {
            $tags[] = WhatsAppContactKind::Staff;
            $kind = WhatsAppContactKind::Staff;
            $displayName = (string) $staff->name;
        }

        if ($student !== null) {
            $studentKind = $this->studentKind($student);
            $tags[] = $studentKind;
            // Prefer student/lead as the primary CRM contact for WhatsApp parent chats.
            $kind = $studentKind;
            $displayName = (string) $student->name;
        }

        return new WhatsAppInboxContact(
            displayName: $displayName !== '' ? $displayName : 'Unknown contact',
            kind: $kind,
            tags: $tags,
            student: $student,
            staff: $staff,
        );
    }

    protected function studentKind(Student $student): WhatsAppContactKind
    {
        $status = $student->status instanceof StudentStatus
            ? $student->status
            : StudentStatus::tryFrom((string) $student->status);

        return $status === StudentStatus::Enquiry
            ? WhatsAppContactKind::Lead
            : WhatsAppContactKind::Student;
    }

    /**
     * @param  list<string>  $tenDigits
     * @return array<string, Student>
     */
    protected function studentsByTenDigit(array $tenDigits): array
    {
        if ($tenDigits === []) {
            return [];
        }

        $map = [];

        Student::query()
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->where(function ($query) use ($tenDigits): void {
                foreach ($tenDigits as $ten) {
                    $query->orWhere('mobile', $ten)
                        ->orWhere('mobile', '91'.$ten)
                        ->orWhere('mobile', 'like', '%'.$ten);
                }
            })
            ->orderByDesc('id')
            ->get(['id', 'name', 'mobile', 'status'])
            ->each(function (Student $student) use (&$map): void {
                $ten = $this->tenDigit((string) $student->mobile);

                if ($ten === '' || isset($map[$ten])) {
                    return;
                }

                $map[$ten] = $student;
            });

        return $map;
    }

    /**
     * Match users.mobile and staff_profiles.mobile (both stored as 10-digit usually).
     *
     * @param  list<string>  $tenDigits
     * @return array<string, User>
     */
    protected function staffByTenDigit(array $tenDigits): array
    {
        if ($tenDigits === []) {
            return [];
        }

        $map = [];

        User::query()
            ->where('is_active', true)
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->where(function ($query) use ($tenDigits): void {
                foreach ($tenDigits as $ten) {
                    $query->orWhere('mobile', $ten)
                        ->orWhere('mobile', '91'.$ten)
                        ->orWhere('mobile', 'like', '%'.$ten);
                }
            })
            ->orderByDesc('id')
            ->get(['id', 'name', 'mobile'])
            ->each(function (User $user) use (&$map): void {
                $ten = $this->tenDigit((string) $user->mobile);

                if ($ten === '' || isset($map[$ten])) {
                    return;
                }

                $map[$ten] = $user;
            });

        StaffProfile::query()
            ->with(['user:id,name,mobile,is_active'])
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->where(function ($query) use ($tenDigits): void {
                foreach ($tenDigits as $ten) {
                    $query->orWhere('mobile', $ten)
                        ->orWhere('mobile', '91'.$ten)
                        ->orWhere('mobile', 'like', '%'.$ten);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->each(function (StaffProfile $profile) use (&$map): void {
                $user = $profile->user;

                if (! $user || ! $user->is_active) {
                    return;
                }

                $ten = $this->tenDigit((string) $profile->mobile);

                if ($ten === '' || isset($map[$ten])) {
                    return;
                }

                $map[$ten] = $user;
            });

        return $map;
    }

    protected function tenDigit(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 10) {
            return $digits;
        }

        return '';
    }
}
