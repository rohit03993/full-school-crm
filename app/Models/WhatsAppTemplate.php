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

        $mappings = $this->param_mappings ?? [];

        if ($mappings === []) {
            $bodyVariables = data_get($this->provider_meta, 'body_variables', []);

            if (is_array($bodyVariables) && $bodyVariables !== []) {
                return WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount);
            }
        }

        $sources = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $sources[] = $mappings[$i] ?? null;
        }

        return $sources;
    }
}
