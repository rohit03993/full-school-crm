<?php

namespace App\Models;

use App\Support\WhatsAppTemplateParamMappingInferrer;
use Illuminate\Database\Eloquent\Model;

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
