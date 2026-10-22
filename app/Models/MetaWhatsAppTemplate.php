<?php

namespace App\Models;

use App\Support\MetaWhatsAppTemplateBuilder;
use App\Support\MetaWhatsAppTemplateParser;
use App\Support\WhatsAppTemplateParamMappingInferrer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaWhatsAppTemplate extends Model
{
    protected $table = 'meta_whatsapp_templates';

    protected $fillable = [
        'name',
        'language',
        'status',
        'param_count',
        'param_mappings',
        'body',
        'components',
        'provider_meta',
        'is_active',
        'synced_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'param_count' => 'integer',
            'param_mappings' => 'array',
            'components' => 'array',
            'provider_meta' => 'array',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return list<string|null>
     */
    public function resolvedParamMappings(): array
    {
        $paramCount = (int) $this->param_count;

        if ($paramCount < 1) {
            return [];
        }

        $bodyVariables = data_get($this->provider_meta, 'body_variables', []);
        $bodyVariables = is_array($bodyVariables) ? array_values($bodyVariables) : [];
        $inferred = WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount, (string) $this->name);
        $mappings = $this->param_mappings ?? [];
        $sources = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $stored = $mappings[$i] ?? null;
            $sources[] = filled($stored) ? (string) $stored : ($inferred[$i] ?? null);
        }

        if ($this->looksLikeAttendancePunchName((string) $this->name)) {
            $defaults = WhatsAppTemplateParamMappingInferrer::attendancePunchDefaults($paramCount);

            foreach ($defaults as $index => $default) {
                if (! filled($sources[$index] ?? null) && filled($default)) {
                    $sources[$index] = $default;
                }
            }
        }

        return $sources;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function liveCampaigns(): HasMany
    {
        return $this->hasMany(WhatsAppLiveCampaign::class, 'meta_whatsapp_template_id');
    }

    /**
     * The bucket Meta approved (Utility, Marketing, or Authentication).
     * Empty until Sync from Meta has stored it.
     */
    public function metaCategoryCode(): string
    {
        return strtoupper(trim((string) data_get($this->provider_meta, 'category', '')));
    }

    public function approvedCategoryLabel(): string
    {
        $category = $this->metaCategoryCode();

        if ($category === '') {
            return '';
        }

        $label = self::categoryWord($category);
        $previous = strtoupper(trim((string) data_get($this->provider_meta, 'previous_category', '')));

        if ($previous !== '' && $previous !== $category) {
            return $label.' (was '.self::categoryWord($previous).')';
        }

        return $label;
    }

    public function approvedCategoryHint(): string
    {
        $category = $this->metaCategoryCode();

        if ($category === '') {
            return 'Click Sync from Meta. This is the bucket WhatsApp approved.';
        }

        $previous = strtoupper(trim((string) data_get($this->provider_meta, 'previous_category', '')));

        if ($previous !== '' && $previous !== $category) {
            return 'We sent this as '.self::categoryWord($previous).'. WhatsApp moved it to '.self::categoryWord($category).'.';
        }

        return 'WhatsApp approved this template as '.self::categoryWord($category).'.';
    }

    public static function categoryWord(string $code): string
    {
        return match (strtoupper(trim($code))) {
            'UTILITY' => 'Utility',
            'MARKETING' => 'Marketing',
            'AUTHENTICATION' => 'Authentication',
            '' => 'Unknown',
            default => ucfirst(strtolower(trim($code))),
        };
    }

    /**
     * Null means the message words can be sent to WhatsApp again.
     */
    public function wordingLockReason(): ?string
    {
        $category = (string) data_get($this->provider_meta, 'category', '');

        if (MetaWhatsAppTemplateBuilder::isAuthenticationOtp((string) $this->name, $category) || $this->hasAuthenticationComponent()) {
            return 'Login code templates use fixed WhatsApp wording. The words cannot be changed here.';
        }

        $header = MetaWhatsAppTemplateParser::headerFormat(is_array($this->components) ? $this->components : []);

        if ($header === null) {
            $stored = strtoupper(trim((string) data_get($this->provider_meta, 'header_format', '')));
            $header = in_array($stored, ['IMAGE', 'VIDEO', 'DOCUMENT', 'TEXT'], true) ? $stored : null;
        }

        if (in_array($header, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return 'This template has a photo or video header. Changing the words here would remove that header. Create a new template instead, so this one keeps working.';
        }

        $status = strtoupper((string) $this->status);

        if ($status === 'PENDING') {
            return 'WhatsApp is still reviewing this template. You can change the words after the status is Approved or Rejected.';
        }

        if (! in_array($status, ['APPROVED', 'REJECTED', 'PAUSED'], true)) {
            return 'WhatsApp status is '.$status.'. The words can be changed when the status is Approved, Rejected, or Paused.';
        }

        return null;
    }

    protected function hasAuthenticationComponent(): bool
    {
        foreach ($this->components ?? [] as $component) {
            if (! is_array($component)) {
                continue;
            }

            if (! empty($component['add_security_recommendation'])) {
                return true;
            }

            foreach ($component['buttons'] ?? [] as $button) {
                if (is_array($button) && strtoupper((string) ($button['type'] ?? '')) === 'OTP') {
                    return true;
                }
            }
        }

        return false;
    }

    protected function looksLikeAttendancePunchName(string $name): bool
    {
        $normalized = strtolower(trim($name));

        if ($normalized === '') {
            return false;
        }

        foreach ([
            'manual_in',
            'manual_out',
            'punch_in',
            'punch_out',
            'check_in',
            'check_out',
            'checkin',
            'checkout',
            'attendance',
            'parent_attendance',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
