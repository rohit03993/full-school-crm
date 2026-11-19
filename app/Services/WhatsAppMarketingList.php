<?php

namespace App\Services;

use App\Models\WhatsAppMarketingContact;

class WhatsAppMarketingList
{
    /**
     * @param  list<string|null>  $headers
     * @param  list<list<string|null>>  $rows
     * @return array{added: int, skipped: int}
     */
    public function importGrid(array $headers, array $rows): array
    {
        $mobileColumn = $this->column($headers, ['mobile', 'phone', 'number', 'whatsapp']);
        $nameColumn = $this->column($headers, ['name', 'student', 'parent']);

        if ($mobileColumn === null) {
            return ['added' => 0, 'skipped' => 0];
        }

        $added = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $mobile = $this->mobile((string) ($row[$mobileColumn] ?? ''));

            if ($mobile === null) {
                $skipped++;

                continue;
            }

            $extra = [];

            foreach ($headers as $index => $header) {
                if ($index === $mobileColumn || $index === $nameColumn || $header === null || $header === '') {
                    continue;
                }

                $value = trim((string) ($row[$index] ?? ''));

                if ($value !== '') {
                    $extra[$header] = $value;
                }
            }

            $result = $this->add(
                trim((string) ($nameColumn === null ? '' : ($row[$nameColumn] ?? ''))),
                $mobile,
                'excel',
                $extra,
            );

            if ($result === 'added') {
                $added++;
            } else {
                $skipped++;
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, string>  $extra
     */
    public function add(string $name, string $mobile, string $source, array $extra = []): string
    {
        $mobile = $this->mobile($mobile);

        if ($mobile === null) {
            return 'invalid';
        }

        if (WhatsAppMarketingContact::query()->where('mobile', $mobile)->exists()) {
            return 'duplicate';
        }

        WhatsAppMarketingContact::query()->create([
            'name' => $name !== '' ? mb_substr($name, 0, 120) : null,
            'mobile' => $mobile,
            'extra' => $extra === [] ? null : $extra,
            'source' => $source,
        ]);

        return 'added';
    }

    public function mobile(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        if (strlen($digits) !== 10) {
            return null;
        }

        return $digits;
    }

    /**
     * @param  list<string|null>  $headers
     * @param  list<string>  $names
     */
    protected function column(array $headers, array $names): ?int
    {
        foreach ($headers as $index => $header) {
            $label = strtolower(trim((string) $header));

            if (in_array($label, $names, true)) {
                return (int) $index;
            }
        }

        return null;
    }
}
