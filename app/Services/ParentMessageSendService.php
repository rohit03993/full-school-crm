<?php

namespace App\Services;

use App\Models\ParentMessageSend;
use App\Models\User;
use App\Support\CrmPagination;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
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
            ->get(['id', 'sent_by_user_id', 'kind', 'is_resend', 'batch_id']);

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::query()
            ->whereIn('id', $rows->pluck('sent_by_user_id')->filter()->all())
            ->pluck('name', 'id');
        $firstHomeworkSendIdByBatch = $this->firstHomeworkSendIdByBatch();

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

            $isResend = $row->kind === ParentMessageSend::Homework
                ? $this->homeworkClickIsResend($row, $firstHomeworkSendIdByBatch)
                : (bool) $row->is_resend;

            $bucket = match (true) {
                $row->kind === ParentMessageSend::Homework && $isResend => 'homework_resends',
                $row->kind === ParentMessageSend::Homework => 'homework_sends',
                $row->kind === ParentMessageSend::ExamMarks && $isResend => 'exam_resends',
                $row->kind === ParentMessageSend::ExamMarks => 'exam_sends',
                default => null,
            };

            if ($bucket !== null) {
                $byStaff[$key][$bucket]++;
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
     * @return LengthAwarePaginator<int, array{at: string, name: string, kind: string, place: string, repeat: string, parents: int}>
     */
    public function recent(Carbon $from, Carbon $to, ?int $perPage = null, ?int $page = null): LengthAwarePaginator
    {
        $perPage = $perPage ?? CrmPagination::PER_PAGE;
        $page = max(1, $page ?? 1);

        if (! Schema::hasTable('parent_message_sends')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $firstHomeworkSendIdByBatch = $this->firstHomeworkSendIdByBatch();

        return ParentMessageSend::query()
            ->with('sentBy')
            ->whereBetween('sent_at', [$from, $to])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(function (ParentMessageSend $send) use ($firstHomeworkSendIdByBatch): array {
                $isResend = $send->kind === ParentMessageSend::Homework
                    ? $this->homeworkClickIsResend($send, $firstHomeworkSendIdByBatch)
                    : (bool) $send->is_resend;

                return [
                    'at' => $send->sent_at?->timezone((string) config('app.timezone'))->format('d M Y, h:i A') ?? '—',
                    'name' => (string) ($send->sentBy?->name ?? 'Unknown staff'),
                    'kind' => $send->kindLabel(),
                    'place' => (string) $send->label,
                    'repeat' => $isResend ? 'Resend' : 'First send',
                    'parents' => (int) $send->parent_count,
                ];
            });
    }

    /**
     * The first homework click for a class stays First send. Every later click for that same class is a Resend.
     *
     * @param  array<int, int>  $firstHomeworkSendIdByBatch
     */
    public function homeworkClickIsResend(ParentMessageSend $send, array $firstHomeworkSendIdByBatch): bool
    {
        $batchId = (int) ($send->batch_id ?? 0);

        if ($batchId < 1) {
            return (bool) $send->is_resend;
        }

        $firstId = $firstHomeworkSendIdByBatch[$batchId] ?? null;

        return $firstId !== null && (int) $send->id !== (int) $firstId;
    }

    /**
     * @return array<int, int>
     */
    public function firstHomeworkSendIdByBatch(): array
    {
        if (! Schema::hasTable('parent_message_sends')) {
            return [];
        }

        return ParentMessageSend::query()
            ->where('kind', ParentMessageSend::Homework)
            ->whereNotNull('batch_id')
            ->selectRaw('batch_id, MIN(id) as first_id')
            ->groupBy('batch_id')
            ->pluck('first_id', 'batch_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
