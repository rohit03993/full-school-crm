<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\HomeworkAssignment;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Support\CombinedHomeworkWhatsAppTemplate;
use App\Support\HomeworkShareWhatsAppTemplate;
use Illuminate\Support\Collection;
use Throwable;

class HomeworkWhatsAppService
{
    public function __construct(
        protected WhatsAppDispatchService $whatsapp,
        protected HomeworkAssignmentService $homework,
    ) {}

    /**
     * Approved Meta templates usable for homework share (4 body params).
     *
     * @return array<string, string> name => label
     */
    public function shareTemplateOptions(): array
    {
        return $this->approvedShareTemplates()
            ->mapWithKeys(fn (MetaWhatsAppTemplate $template): array => [
                $template->name => $template->name.' ('.$template->language.', '.$template->param_count.' params)',
            ])
            ->all();
    }

    public function defaultShareTemplateName(): ?string
    {
        $preferred = array_values(array_unique(array_filter([
            (string) Setting::getValue('whatsapp.homework_template_name', ''),
            (string) config('whatsapp.homework_template_name', ''),
            ...HomeworkShareWhatsAppTemplate::ALIASES,
            HomeworkShareWhatsAppTemplate::NAME,
        ])));

        $templates = $this->approvedShareTemplates();

        foreach ($preferred as $name) {
            $match = $templates->first(
                fn (MetaWhatsAppTemplate $template): bool => $template->name === $name
            );

            if ($match) {
                return $match->name;
            }
        }

        return $templates->first()?->name;
    }

    /**
     * Default APPROVED 4-param template for the combined daily homework message.
     */
    public function defaultCombinedTemplateName(): ?string
    {
        $preferred = array_values(array_unique(array_filter([
            (string) Setting::getValue('whatsapp.homework_combined_template_name', ''),
            ...CombinedHomeworkWhatsAppTemplate::ALIASES,
        ])));

        $templates = $this->approvedShareTemplates();

        foreach ($preferred as $name) {
            $match = $templates->first(
                fn (MetaWhatsAppTemplate $template): bool => $template->name === $name
            );

            if ($match) {
                return $match->name;
            }
        }

        return $this->defaultShareTemplateName();
    }

    /**
     * Send ONE combined homework message per student for a class/date.
     *
     * Each approved assignment contributes one line "Subject: public link" to {{4}}. Subjects
     * without homework are simply not included.
     *
     * @param  Collection<int, HomeworkAssignment>  $assignments
     * @return array{sent: int, failed: int, skipped: int, error: ?string, template: ?string}
     */
    public function notifyCombined(
        Batch $batch,
        string $dateLabel,
        Collection $assignments,
        ?string $templateName = null,
    ): array {
        $empty = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'error' => null, 'template' => null];

        if (! $this->whatsapp->isConfigured()) {
            return [...$empty, 'error' => 'WhatsApp is not configured. Open WhatsApp → WhatsApp setup.'];
        }

        if ($assignments->isEmpty()) {
            return [...$empty, 'error' => 'No approved homework for this class and date yet.'];
        }

        $resolved = filled($templateName) ? trim($templateName) : $this->defaultCombinedTemplateName();

        if (blank($resolved)) {
            return [
                ...$empty,
                'error' => 'No approved 4-parameter Meta template found. Create/approve homework_combined, then Sync templates.',
            ];
        }

        if ($this->whatsapp->resolveMetaTemplatePublic($resolved) === null) {
            return [
                ...$empty,
                'template' => $resolved,
                'error' => 'Template "'.$resolved.'" is not approved/synced. Open WhatsApp → Templates, Sync, and use an APPROVED 4-param template.',
            ];
        }

        $linksBlock = $this->buildSubjectLinksBlock($assignments);

        if ($linksBlock === '') {
            return [...$empty, 'template' => $resolved, 'error' => 'None of the selected homework has a shareable link.'];
        }

        $students = $this->homework->batchStudentsWithMobile($batch->id);

        if ($students->isEmpty()) {
            return [...$empty, 'template' => $resolved, 'error' => 'No students with a Mobile number in this class.'];
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $lastError = null;

        foreach ($students as $student) {
            if (blank($student->mobile)) {
                $skipped++;

                continue;
            }

            $roll = (string) ($student->activeEnrollment?->enrollment_number ?? '');

            $params = [
                (string) ($student->name ?? 'Student'),
                $roll !== '' ? $roll : '—',
                $dateLabel,
                $linksBlock,
            ];

            // One student's failure must not stop the rest of the class.
            try {
                $result = $this->whatsapp->send(
                    (string) $student->mobile,
                    $params,
                    $resolved,
                    (string) ($student->name ?? 'Student'),
                    4,
                );
            } catch (Throwable $exception) {
                $failed++;
                $lastError = $exception->getMessage();

                continue;
            }

            if (($result['status'] ?? '') === 'success') {
                $sent++;
            } else {
                $failed++;
                $lastError = (string) ($result['error'] ?? $lastError);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'error' => $sent === 0 && $failed === 0
                ? ($lastError ?? 'No WhatsApp messages were sent.')
                : ($failed > 0 ? $lastError : null),
            'template' => $resolved,
        ];
    }

    /**
     * Meta rejects newlines/tabs inside template parameters (#132018), so subjects are joined
     * on one line with " | " instead of line breaks.
     *
     * @param  Collection<int, HomeworkAssignment>  $assignments
     */
    protected function buildSubjectLinksBlock(Collection $assignments): string
    {
        return $assignments
            ->map(function (HomeworkAssignment $assignment): ?string {
                $label = $assignment->courseSubject?->displayLabel()
                    ?? $assignment->title
                    ?? 'Homework';

                $link = $assignment->publicUrl();

                if (blank($link)) {
                    return null;
                }

                $safeLabel = preg_replace('/\s+/u', ' ', trim((string) $label)) ?? trim((string) $label);

                return $safeLabel.': '.$link;
            })
            ->filter()
            ->implode(' | ');
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, error: ?string, template: ?string}
     */
    public function notifyBatch(HomeworkAssignment $assignment, ?string $templateName = null): array
    {
        $empty = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'error' => null, 'template' => null];

        if (! $this->whatsapp->isConfigured()) {
            return [...$empty, 'error' => 'WhatsApp is not configured. Open WhatsApp → WhatsApp setup.'];
        }

        $resolved = filled($templateName) ? trim($templateName) : $this->defaultShareTemplateName();

        if (blank($resolved)) {
            return [
                ...$empty,
                'error' => 'No approved 4-parameter Meta template found. Create/approve homework_api (or homework_update), then Sync templates.',
            ];
        }

        if ($this->whatsapp->resolveMetaTemplatePublic($resolved) === null) {
            return [
                ...$empty,
                'template' => $resolved,
                'error' => 'Template "'.$resolved.'" is not approved/synced. Open WhatsApp → Templates, Sync, and use an APPROVED 4-param template.',
            ];
        }

        $link = $assignment->publicUrl();
        $students = $this->homework->batchStudentsWithMobile($assignment->batch_id);

        if ($students->isEmpty()) {
            return [
                ...$empty,
                'template' => $resolved,
                'error' => 'No students with a Mobile number in this batch.',
            ];
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $lastError = null;

        foreach ($students as $student) {
            $result = $this->notifyStudent($student, $assignment, $resolved, $link);

            if ($result['status'] === 'sent') {
                $sent++;
            } elseif ($result['status'] === 'failed') {
                $failed++;
                $lastError = $result['error'] ?? $lastError;
            } else {
                $skipped++;
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'error' => $sent === 0 && $failed === 0
                ? ($lastError ?? 'No WhatsApp messages were sent.')
                : ($failed > 0 ? $lastError : null),
            'template' => $resolved,
        ];
    }

    /**
     * @return array{status: 'sent'|'failed'|'skipped', error?: string}
     */
    public function notifyStudent(
        Student $student,
        HomeworkAssignment $assignment,
        ?string $templateName = null,
        ?string $link = null,
    ): array {
        if (! $this->whatsapp->isConfigured()) {
            return ['status' => 'skipped', 'error' => 'WhatsApp is not configured.'];
        }

        $resolved = filled($templateName) ? trim($templateName) : $this->defaultShareTemplateName();

        if (blank($resolved) || blank($student->mobile)) {
            return ['status' => 'skipped', 'error' => blank($resolved) ? 'No share template selected.' : 'Student has no mobile.'];
        }

        $link ??= $assignment->publicUrl();
        $roll = (string) ($student->activeEnrollment?->enrollment_number ?? '');

        $params = [
            (string) ($student->name ?? 'Student'),
            $roll !== '' ? $roll : '—',
            (string) $assignment->title,
            $link,
        ];

        $result = $this->whatsapp->send(
            (string) $student->mobile,
            $params,
            $resolved,
            (string) ($student->name ?? 'Student'),
            4,
        );

        if (($result['status'] ?? '') === 'success') {
            return ['status' => 'sent'];
        }

        return [
            'status' => 'failed',
            'error' => (string) ($result['error'] ?? 'WhatsApp send failed.'),
        ];
    }

    /**
     * @return Collection<int, MetaWhatsAppTemplate>
     */
    protected function approvedShareTemplates(): Collection
    {
        return MetaWhatsAppTemplate::query()
            ->whereRaw('UPPER(status) = ?', ['APPROVED'])
            ->where('param_count', 4)
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereRaw('UPPER(status) = ?', ['APPROVED']);
            })
            ->orderBy('name')
            ->orderBy('language')
            ->get()
            ->unique('name')
            ->values();
    }
}
