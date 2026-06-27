<?php

namespace App\Models;

use App\Support\WhatsAppTemplateParamMappingInferrer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'description',
        'param_count',
        'param_mappings',
        'body',
        'is_active',
        'synced_at',
        'provider_meta',
    ];

    protected function casts(): array
    {
        return [
            'param_count' => 'integer',
            'param_mappings' => 'array',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
            'provider_meta' => 'array',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(WhatsAppCampaign::class, 'whatsapp_template_id');
    }

    /**
     * @return list<string|null>
     */
    public function paramSources(): array
    {
        return $this->resolvedParamMappings();
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
        $inferred = WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount);
        $mappings = $this->param_mappings ?? [];
        $sources = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $stored = $mappings[$i] ?? null;
            $sources[] = filled($stored) ? (string) $stored : ($inferred[$i] ?? null);
        }

        if ($this->looksLikeMarksTemplate($bodyVariables)) {
            $defaults = [
                'student.name',
                'student.enrollment_number',
                'activity.test_name',
                'activity.marks_summary',
            ];

            foreach ($defaults as $index => $default) {
                if (! filled($sources[$index] ?? null)) {
                    $sources[$index] = $default;
                }
            }
        }

        return $sources;
    }

    /**
     * Persist inferred mappings when the stored row only has blank/null slots.
     */
    public function ensureParamMappings(): self
    {
        $resolved = $this->resolvedParamMappings();
        $stored = $this->param_mappings ?? [];

        if ($this->mappingsNeedPersisting($stored, $resolved)) {
            $this->update(['param_mappings' => $resolved]);
            $this->refresh();
        }

        return $this;
    }

    /**
     * @param  list<mixed>  $bodyVariables
     */
    protected function looksLikeMarksTemplate(array $bodyVariables): bool
    {
        $normalized = array_map(
            fn (mixed $variable): string => WhatsAppTemplateParamMappingInferrer::normalize((string) $variable),
            $bodyVariables,
        );

        return in_array('tes', $normalized, true)
            || in_array('all_subject_marks', $normalized, true)
            || in_array('test_name', $normalized, true)
            || in_array('marks_summary', $normalized, true);
    }

    /**
     * @param  array<int|string, mixed>  $stored
     * @param  list<string|null>  $resolved
     */
    protected function mappingsNeedPersisting(array $stored, array $resolved): bool
    {
        if ($resolved === []) {
            return false;
        }

        if (! $this->hasFilledMappings($stored)) {
            return $this->hasFilledMappings($resolved);
        }

        foreach ($resolved as $index => $source) {
            if (filled($source) && ! filled($stored[$index] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, mixed>  $mappings
     */
    protected function hasFilledMappings(array $mappings): bool
    {
        return collect($mappings)->contains(fn (mixed $mapping): bool => filled($mapping));
    }
}
