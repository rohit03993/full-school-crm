<?php

namespace App\Services;

use App\Enums\CampusVisitPurpose;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\LicenseFeature;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\VisitStatus;
use App\Enums\WhatsAppInchatAudience;
use App\Enums\WhatsAppInchatFieldType;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppSendActor;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Models\WhatsAppInchatForm;
use App\Models\WhatsAppInchatFormField;
use App\Support\CrmCacheInvalidator;
use App\Support\DefaultCourse;
use App\Support\FeatureGate;
use App\Support\MetaWhatsAppInboundMessageParser;
use App\Support\WhatsAppCampusVisitFlowJson;
use App\Support\WhatsAppInchatFlowJson;
use App\Support\WhatsAppInboxBodyPreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WhatsAppInchatFormService
{
    public function __construct(
        protected WhatsAppFlowService $flows,
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppMessageLogger $logger,
        protected StudentWhatsAppThreadService $thread,
        protected WhatsAppProviderResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     * @return array{status: string, form?: WhatsAppInchatForm, error?: string}
     */
    public function save(array $state, ?int $formId = null): array
    {
        $name = trim((string) ($state['name'] ?? ''));
        $audience = WhatsAppInchatAudience::tryFrom((string) ($state['audience'] ?? ''));
        $word = $this->normalizeWord((string) ($state['trigger_word'] ?? ''));
        $button = trim((string) ($state['button_label'] ?? ''));
        $button = $button !== '' ? mb_substr($button, 0, 20) : 'Open form';
        $fields = is_array($state['fields'] ?? null) ? $state['fields'] : [];

        if ($name === '') {
            return ['status' => 'failed', 'error' => 'Type a name for the form.'];
        }

        if ($audience === null) {
            return ['status' => 'failed', 'error' => 'Choose who this form is for.'];
        }

        if ($word === '' || ! preg_match('/^[a-z][a-z0-9]{0,19}$/', $word)) {
            return ['status' => 'failed', 'error' => 'The word must be one word, using letters and numbers.'];
        }

        if ($word === 'visit') {
            return ['status' => 'failed', 'error' => 'The word visit is already used by the campus visit form.'];
        }

        if ($fields === []) {
            return ['status' => 'failed', 'error' => 'Add at least one question.'];
        }

        if (count($fields) > 8) {
            return ['status' => 'failed', 'error' => 'A form can have up to 8 questions.'];
        }

        $duplicate = WhatsAppInchatForm::query()
            ->where('audience', $audience->value)
            ->where('trigger_word', $word)
            ->when($formId, fn ($query) => $query->where('id', '!=', $formId))
            ->exists();

        if ($duplicate) {
            return ['status' => 'failed', 'error' => 'This word is already used for that group.'];
        }

        $prepared = $this->prepareFields($fields);

        if ($prepared === []) {
            return ['status' => 'failed', 'error' => 'Add at least one question with a label.'];
        }

        $form = DB::transaction(function () use ($formId, $name, $audience, $word, $button, $state, $prepared): WhatsAppInchatForm {
            $form = $formId ? WhatsAppInchatForm::query()->find($formId) : null;

            if (! $form) {
                $form = new WhatsAppInchatForm;
            }

            $wasPublished = $form->exists && $form->status === 'published';

            $form->fill([
                'name' => mb_substr($name, 0, 80),
                'audience' => $audience,
                'trigger_word' => $word,
                'button_label' => $button,
                'enabled' => ! empty($state['enabled']),
                'needs_republish' => $wasPublished,
            ]);
            $form->save();

            $keep = [];

            foreach ($prepared as $index => $row) {
                $field = null;

                if (filled($row['id'])) {
                    $field = $form->fields()->whereKey((int) $row['id'])->first();
                }

                if (! $field) {
                    $field = new WhatsAppInchatFormField([
                        'whatsapp_inchat_form_id' => $form->id,
                    ]);
                }

                $field->fill([
                    'sort' => $index,
                    'field_type' => $row['field_type'],
                    'label' => $row['label'],
                    'is_required' => $row['is_required'],
                    'choices' => $row['choices'],
                ]);
                $field->save();
                $keep[] = $field->id;
            }

            $form->fields()->whereNotIn('id', $keep)->delete();

            return $form->fresh(['fields']);
        });

        return ['status' => 'success', 'form' => $form];
    }

    /**
     * @return array{status: string, error?: string, flow_id?: string}
     */
    public function publish(WhatsAppInchatForm $form): array
    {
        $form->load('fields');

        if ($form->fields->isEmpty()) {
            return ['status' => 'failed', 'error' => 'Add at least one question before publishing.'];
        }

        if (blank($form->flow_token)) {
            $form->forceFill(['flow_token' => 'form_'.$form->id])->save();
        }

        $result = $this->flows->publishNamedFlow(
            'inchat_'.$form->id,
            WhatsAppInchatFlowJson::encoded($form),
            $form->flow_id,
        );

        if (($result['status'] ?? '') !== 'success') {
            return $result;
        }

        $form->update([
            'flow_id' => $result['flow_id'],
            'status' => 'published',
            'needs_republish' => false,
            'published_at' => now(),
        ]);

        return ['status' => 'success', 'flow_id' => (string) $result['flow_id']];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $message
     */
    public function handleInbound(string $phone, array $parsed, array $message, ?string $wamid, ?MetaWhatsAppMessage $record = null): void
    {
        try {
            $response = MetaWhatsAppInboundMessageParser::flowResponse($message);

            if ($response !== null) {
                $this->saveFinishedForm($phone, $response, $wamid, $record);

                return;
            }

            if (($parsed['message_type'] ?? '') !== 'text') {
                return;
            }

            $word = $this->normalizeWord((string) ($parsed['body_preview'] ?? ''));

            if ($word === '' || $word === 'visit') {
                return;
            }

            $form = $this->formForWord($phone, $word);

            if (! $form) {
                return;
            }

            $student = $this->studentForPhone($phone);
            $this->sendForm($form, $phone, null, $student?->id, WhatsAppMessageSource::Automation);
        } catch (\Throwable $exception) {
            Log::error('WhatsApp in-chat form failed', [
                'phone' => $phone,
                'wamid' => $wamid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{status: string, error?: string, message_id?: string}
     */
    public function sendForm(
        WhatsAppInchatForm $form,
        string $phone,
        ?User $sender = null,
        ?int $studentId = null,
        WhatsAppMessageSource $source = WhatsAppMessageSource::Inbox,
    ): array {
        if (! $this->resolver->isMetaActive()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not enabled. Open Connection & Setup and turn on WhatsApp.'];
        }

        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return ['status' => 'failed', 'error' => 'WhatsApp module is not enabled.'];
        }

        if (! $form->enabled || $form->status !== 'published' || blank($form->flow_id)) {
            return ['status' => 'failed', 'error' => 'This form is not published yet.'];
        }

        $phone = $this->thread->normalizePhoneForStorage($phone);

        if ($phone === '') {
            return ['status' => 'failed', 'error' => 'No mobile number for this chat.'];
        }

        if (! $this->thread->sessionOpenForPhone($phone, $studentId)) {
            return [
                'status' => 'failed',
                'error' => 'The 24-hour reply window is closed. Ask them to message you first.',
            ];
        }

        $result = $this->flows->sendInteractiveFlow(
            $phone,
            (string) $form->flow_id,
            (string) $form->flow_token,
            WhatsAppInchatFlowJson::SCREEN_ID,
            $form->name,
            'Tap the button, answer the questions, then tap Finish.',
            $form->button_label,
        );

        if (($result['status'] ?? '') === 'success') {
            $this->logger->recordOutboundText(
                $phone,
                $result['message_id'] ?? null,
                $form->name,
                MetaWhatsAppMessageStatus::Sent,
                'In-chat form',
                is_array($result['response'] ?? null) ? $result['response'] : null,
                $studentId,
                [
                    'message_source' => $source->value,
                    'sent_by_user_id' => $sender?->id,
                    'send_actor' => $sender ? WhatsAppSendActor::Staff->value : WhatsAppSendActor::Automatic->value,
                ],
            );
        }

        return $result;
    }

    /**
     * @return array<int|string, string>
     */
    public function optionsForChat(?Student $student): array
    {
        $audiences = [WhatsAppInchatAudience::Anyone->value];

        if ($student && $student->activeEnrollment()->exists()) {
            $audiences[] = WhatsAppInchatAudience::ExistingStudent->value;
        } elseif ($student && $student->enquiries()->exists()) {
            $audiences[] = WhatsAppInchatAudience::VisitingLead->value;
            $audiences[] = WhatsAppInchatAudience::EducationLead->value;
        } else {
            $audiences[] = WhatsAppInchatAudience::EducationLead->value;
        }

        return WhatsAppInchatForm::query()
            ->where('enabled', true)
            ->where('status', 'published')
            ->whereNotNull('flow_id')
            ->whereIn('audience', $audiences)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (WhatsAppInchatForm $form): array => [
                $form->id => $form->name.' ('.$form->trigger_word.')',
            ])
            ->all();
    }

    public function formForChat(?Student $student, int $formId): ?WhatsAppInchatForm
    {
        if (! array_key_exists($formId, $this->optionsForChat($student))) {
            return null;
        }

        return WhatsAppInchatForm::query()->find($formId);
    }

    /**
     * @return list<array{id: int, name: string, audience: string, trigger_word: string, state: string}>
     */
    public function listRows(): array
    {
        return WhatsAppInchatForm::query()
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppInchatForm $form): array => [
                'id' => $form->id,
                'name' => $form->name,
                'audience' => $form->audience->label(),
                'trigger_word' => $form->trigger_word,
                'state' => $form->stateLabel(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formState(WhatsAppInchatForm $form): array
    {
        $form->load('fields');

        return [
            'name' => $form->name,
            'audience' => $form->audience->value,
            'trigger_word' => $form->trigger_word,
            'button_label' => $form->button_label,
            'enabled' => $form->enabled,
            'fields' => $form->fields->map(fn (WhatsAppInchatFormField $field): array => [
                'id' => $field->id,
                'field_type' => $field->field_type->value,
                'label' => $field->label,
                'is_required' => $field->is_required,
                'choices' => implode("\n", $field->choices ?? []),
            ])->all(),
        ];
    }

    public function normalizeWord(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return trim($text, " \t\n\r\0\x0B.!?");
    }

    protected function formForWord(string $phone, string $word): ?WhatsAppInchatForm
    {
        $student = $this->studentForPhone($phone);
        $audience = WhatsAppInchatAudience::Anyone;

        if ($student && $student->activeEnrollment()->exists()) {
            $audience = WhatsAppInchatAudience::ExistingStudent;
        } elseif ($student && Enquiry::query()->where('student_id', $student->id)->exists()) {
            $audience = WhatsAppInchatAudience::VisitingLead;
        }

        if ($audience !== WhatsAppInchatAudience::ExistingStudent) {
            $educationLead = $this->publishedForm($word, WhatsAppInchatAudience::EducationLead);

            if ($educationLead) {
                return $educationLead;
            }
        }

        $specific = $this->publishedForm($word, $audience);

        if ($specific || $audience === WhatsAppInchatAudience::Anyone) {
            return $specific;
        }

        return $this->publishedForm($word, WhatsAppInchatAudience::Anyone);
    }

    protected function publishedForm(string $word, WhatsAppInchatAudience $audience): ?WhatsAppInchatForm
    {
        return WhatsAppInchatForm::query()
            ->where('enabled', true)
            ->where('status', 'published')
            ->whereNotNull('flow_id')
            ->where('trigger_word', $word)
            ->where('audience', $audience->value)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function saveFinishedForm(string $phone, array $response, ?string $wamid, ?MetaWhatsAppMessage $record): void
    {
        $token = trim((string) ($response['flow_token'] ?? ''));

        if ($token === '' || $token === WhatsAppCampusVisitFlowJson::FLOW_TOKEN) {
            return;
        }

        $form = WhatsAppInchatForm::query()->where('flow_token', $token)->first();

        if (! $form) {
            return;
        }

        $form->load('fields');
        $summary = $this->summary($form, $response);
        $student = $this->studentForPhone($phone);

        if ($record) {
            $record->update([
                'body_preview' => WhatsAppInboxBodyPreview::clip($summary),
            ]);
        }

        if ($form->audience === WhatsAppInchatAudience::EducationLead) {
            $this->recordEducationLead($phone, $form, $response, $summary, $wamid);
        }

        if (filled($wamid) && MetaWhatsAppMessage::query()->where('status_detail', 'inchat:'.$wamid)->exists()) {
            return;
        }

        $this->sendConfirmation($phone, $student, $form, $wamid);
    }

    protected function sendConfirmation(string $phone, ?Student $student, WhatsAppInchatForm $form, ?string $wamid): void
    {
        if (! $this->resolver->isMetaActive()) {
            return;
        }

        $text = 'Thank you. We have received your '.$form->name.'.';
        $result = $this->meta->sendText($phone, $text);

        if (($result['status'] ?? '') !== 'success') {
            Log::warning('WhatsApp in-chat form confirmation failed', [
                'phone' => $phone,
                'error' => $result['error'] ?? null,
            ]);

            return;
        }

        $this->logger->recordOutboundText(
            $phone,
            $result['message_id'] ?? null,
            $text,
            MetaWhatsAppMessageStatus::Sent,
            filled($wamid) ? 'inchat:'.$wamid : 'In-chat form reply',
            is_array($result['response'] ?? null) ? $result['response'] : null,
            $student?->id,
            [
                'message_source' => WhatsAppMessageSource::Automation->value,
                'send_actor' => WhatsAppSendActor::Automatic->value,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function summary(WhatsAppInchatForm $form, array $response): string
    {
        $lines = [$form->name];

        foreach ($form->fields as $field) {
            $value = $this->displayAnswer($field, $response[$field->answerKey()] ?? '');
            $lines[] = $field->label.': '.($value !== '' ? $value : '—');
        }

        return implode("\n", $lines);
    }

    protected function displayAnswer(WhatsAppInchatFormField $field, mixed $raw): string
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return '';
        }

        if ($field->field_type === WhatsAppInchatFieldType::CourseList) {
            if ($value === 'general') {
                return 'General';
            }

            if (ctype_digit($value)) {
                $name = Course::query()->whereKey((int) $value)->value('name');

                return filled($name) ? (string) $name : $value;
            }
        }

        if ($field->field_type === WhatsAppInchatFieldType::Choices && str_starts_with($value, 'c') && ctype_digit(substr($value, 1))) {
            $index = (int) substr($value, 1);
            $choice = $field->choices[$index] ?? null;

            if (filled($choice)) {
                return (string) $choice;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function recordEducationLead(string $phone, WhatsAppInchatForm $form, array $response, string $summary, ?string $wamid): void
    {
        $marker = filled($wamid) ? 'whatsapp_lead:'.$wamid : null;

        if ($marker && Visit::query()->where('remarks', $marker)->exists()) {
            return;
        }

        $student = $this->studentForPhone($phone);

        if ($student && $student->activeEnrollment()->exists()) {
            return;
        }

        $enquiry = $student
            ? Enquiry::query()->where('student_id', $student->id)->latest('id')->first()
            : null;

        if ($enquiry && $student) {
            $this->addLeadAnswers($student, $enquiry, $summary, $marker);

            return;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $tenDigit = strlen($digits) === 12 && str_starts_with($digits, '91')
            ? substr($digits, 2)
            : $digits;

        try {
            app(EnquiryService::class)->create([
                'name' => $this->childName($form, $response),
                'father_name' => 'Not given',
                'date_of_birth' => '2000-01-01',
                'gender' => Gender::Other->value,
                'mobile' => $tenDigit,
                'course_id' => $this->courseIdFromAnswers($form, $response),
                'discussion_summary' => $summary."\nDate of birth was not collected on WhatsApp. Please update the student.",
                'remarks' => $marker,
                'visit_status' => VisitStatus::FollowUpRequired->value,
            ], null, LeadSource::WhatsApp);
        } catch (ValidationException $exception) {
            Log::warning('WhatsApp education lead was not saved', [
                'phone' => $phone,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function addLeadAnswers(Student $student, Enquiry $enquiry, string $summary, ?string $marker): void
    {
        Visit::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => now()->toDateString(),
            'discussion_summary' => $summary,
            'remarks' => $marker,
            'status' => VisitStatus::FollowUpRequired,
            'campus_purpose' => CampusVisitPurpose::General,
        ]);

        $enquiry->update([
            'latest_visit_status' => VisitStatus::FollowUpRequired,
        ]);

        CrmCacheInvalidator::afterVisitOrFollowUpChange($enquiry->meeting_with_user_id);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function childName(WhatsAppInchatForm $form, array $response): string
    {
        foreach ($form->fields as $field) {
            if ($field->field_type !== WhatsAppInchatFieldType::ShortText) {
                continue;
            }

            $value = $this->displayAnswer($field, $response[$field->answerKey()] ?? '');

            if ($value !== '') {
                return mb_substr($value, 0, 120);
            }
        }

        return 'WhatsApp lead';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function courseIdFromAnswers(WhatsAppInchatForm $form, array $response): int
    {
        foreach ($form->fields as $field) {
            if ($field->field_type !== WhatsAppInchatFieldType::CourseList) {
                continue;
            }

            $value = trim((string) ($response[$field->answerKey()] ?? ''));

            if (ctype_digit($value) && Course::query()->whereKey((int) $value)->exists()) {
                return (int) $value;
            }
        }

        return DefaultCourse::undecided()->id;
    }

    protected function studentForPhone(string $phone): ?Student
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $tenDigit = strlen($digits) === 12 && str_starts_with($digits, '91')
            ? substr($digits, 2)
            : $digits;

        return Student::query()->where('mobile', $tenDigit)->first();
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array{id: ?int, field_type: WhatsAppInchatFieldType, label: string, is_required: bool, choices: ?list<string>}>
     */
    protected function prepareFields(array $fields): array
    {
        $prepared = [];

        foreach ($fields as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = WhatsAppInchatFieldType::tryFrom((string) ($row['field_type'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));

            if ($type === null || $label === '') {
                continue;
            }

            $choices = null;

            if ($type === WhatsAppInchatFieldType::Choices) {
                $choices = collect(preg_split('/\r\n|\r|\n/', (string) ($row['choices'] ?? '')) ?: [])
                    ->map(fn (string $choice): string => mb_substr(trim($choice), 0, 30))
                    ->filter(fn (string $choice): bool => $choice !== '')
                    ->unique()
                    ->values()
                    ->take(20)
                    ->all();

                if ($choices === []) {
                    continue;
                }
            }

            $prepared[] = [
                'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                'field_type' => $type,
                'label' => mb_substr($label, 0, 80),
                'is_required' => ! empty($row['is_required']),
                'choices' => $choices,
            ];
        }

        return $prepared;
    }
}
