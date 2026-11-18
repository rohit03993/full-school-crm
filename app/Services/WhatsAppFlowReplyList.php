<?php

namespace App\Services;

use App\Filament\Pages\StudentProfilePage;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppFlowReplyList
{
    /**
     * People who finished a WhatsApp flow. Read from the visit note already saved on the lead.
     *
     * @return list<array{name: string, mobile: string, flow: string, when: string, answers: string, profile_url: ?string}>
     */
    public function rows(string $flow = 'all', int $limit = 50): array
    {
        $query = Visit::query()
            ->with('student:id,name,mobile')
            ->where(function (Builder $query): void {
                $query->where('remarks', 'like', 'whatsapp_lead:%')
                    ->orWhere('remarks', 'like', 'whatsapp_flow:%')
                    ->orWhere('remarks', 'WhatsApp campus visit');
            });

        if ($flow === 'campus') {
            $query->where(function (Builder $query): void {
                $query->where('remarks', 'like', 'whatsapp_flow:%')
                    ->orWhere('remarks', 'WhatsApp campus visit');
            });
        } elseif ($flow !== 'all' && $flow !== '') {
            $name = str_replace(['%', '_'], ['\%', '\_'], $flow);
            $query->where('remarks', 'like', 'whatsapp_lead:%')
                ->where(function (Builder $query) use ($name): void {
                    $query->where('discussion_summary', $name)
                        ->orWhere('discussion_summary', 'like', $name."\n%");
                });
        }

        return $query
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (Visit $visit): array {
                $summary = (string) $visit->discussion_summary;
                $studentId = $visit->student_id;

                return [
                    'name' => $visit->student?->name ?: 'WhatsApp lead',
                    'mobile' => (string) ($visit->student?->mobile ?? ''),
                    'flow' => $this->flowName($summary, (string) $visit->remarks),
                    'when' => $visit->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '',
                    'answers' => $this->answers($summary),
                    'profile_url' => $studentId
                        ? StudentProfilePage::getUrl(['record' => $studentId])
                        : null,
                ];
            })
            ->all();
    }

    protected function flowName(string $summary, string $remarks): string
    {
        $first = trim(strtok($summary, "\n") ?: '');

        if (str_starts_with($remarks, 'whatsapp_flow:') || $remarks === 'WhatsApp campus visit' || str_starts_with($first, 'Campus visit booked')) {
            return 'Campus visit';
        }

        return $first !== '' ? $first : 'Flow';
    }

    protected function answers(string $summary): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $summary) ?: [];
        array_shift($lines);

        $kept = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, 'Date of birth was not collected')) {
                continue;
            }

            $kept[] = $line;
        }

        return implode(' · ', $kept);
    }
}
