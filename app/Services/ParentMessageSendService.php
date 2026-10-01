<?php

namespace App\Services;

use App\Models\ParentMessageSend;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ParentMessageSendService
{
    /**
     * @param  array{
     *     kind: string,
     *     is_resend: bool,
     *     label: string,
     *     sent_by_user_id: int|null,
     *     parent_count?: int,
     *     batch_id?: int|null,
     *     homework_date?: string|null,
     *     test_key?: string|null,
     *     whatsapp_campaign_id?: int|null
     * }  $data
     */
    public function record(array $data): ?ParentMessageSend
    {
        if (! Schema::hasTable('parent_message_sends')) {
            return null;
        }

        return ParentMessageSend::query()->create([
            'kind' => $data['kind'],
            'is_resend' => (bool) $data['is_resend'],
            'batch_id' => $data['batch_id'] ?? null,
            'homework_date' => $data['homework_date'] ?? null,
            'test_key' => $data['test_key'] ?? null,
            'label' => $data['label'],
            'sent_by_user_id' => $data['sent_by_user_id'],
            'parent_count' => (int) ($data['parent_count'] ?? 0),
            'whatsapp_campaign_id' => $data['whatsapp_campaign_id'] ?? null,
            'sent_at' => now(),
        ]);
    }

    /**
     * One row per staff member who clicked Send or Resend in this date range.
     *
     * @return list<array{
     *     user_id: int|null,
     *     name: string,
     *     homework_sends: int,
     *     homework_resends: int,
     *     exam_sends: int,
     *     exam_resends: int
     * }>
     */
    public function staffReport(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('parent_message_sends')) {
            return [];
        }

        $rows = ParentMessageSend::query()
            ->whereBetween('sent_at', [$from, $to])
            ->selectRaw('sent_by_user_id, kind, is_resend, COUNT(*) as send_count')
            ->groupBy('sent_by_user_id', 'kind', 'is_resend')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::query()
            ->whereIn('id', $rows->pluck('sent_by_user_id')->filter()->all())
            ->pluck('name', 'id');

        $byStaff = [];

        foreach ($rows as $row) {
            $userId = $row->sent_by_user_id ? (int) $row->sent_by_user_id : null;
            $key = (string) ($userId ?? 'none');

            $byStaff[$key] ??= [
                'user_id' => $userId,
                'name' => $userId ? (string) ($names[$userId] ?? 'Former staff') : 'Unknown staff',
                'homework_sends' => 0,
                'homework_resends' => 0,
                'exam_sends' => 0,
                'exam_resends' => 0,
            ];

            $bucket = match (true) {
                $row->kind === ParentMessageSend::Homework && (bool) $row->is_resend => 'homework_resends',
                $row->kind === ParentMessageSend::Homework => 'homework_sends',
                $row->kind === ParentMessageSend::ExamMarks && (bool) $row->is_resend => 'exam_resends',
                $row->kind === ParentMessageSend::ExamMarks => 'exam_sends',
                default => null,
            };

            if ($bucket !== null) {
                $byStaff[$key][$bucket] += (int) $row->send_count;
            }
        }

        $report = array_values($byStaff);

        usort($report, function (array $left, array $right): int {
            $leftTotal = $left['homework_sends'] + $left['homework_resends'] + $left['exam_sends'] + $left['exam_resends'];
            $rightTotal = $right['homework_sends'] + $right['homework_resends'] + $right['exam_sends'] + $right['exam_resends'];

            return $rightTotal <=> $leftTotal;
        });

        return $report;
    }

    /**
     * @return list<array{at: string, name: string, kind: string, place: string, repeat: string, parents: int}>
     */
    public function recent(Carbon $from, Carbon $to, int $limit = 40): array
    {
        if (! Schema::hasTable('parent_message_sends')) {
            return [];
        }

        return ParentMessageSend::query()
            ->with('sentBy')
            ->whereBetween('sent_at', [$from, $to])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (ParentMessageSend $send): array {
                return [
                    'at' => $send->sent_at?->timezone((string) config('app.timezone'))->format('d M Y, h:i A') ?? '—',
                    'name' => (string) ($send->sentBy?->name ?? 'Unknown staff'),
                    'kind' => $send->kindLabel(),
                    'place' => (string) $send->label,
                    'repeat' => $send->is_resend ? 'Resend' : 'First send',
                    'parents' => (int) $send->parent_count,
                ];
            })
            ->all();
    }
}
