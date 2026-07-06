<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WhatsAppTemplateCatalog
{
    public function __construct(
        protected WhatsAppProviderResolver $resolver,
    ) {}

    /**
     * Templates staff can pick for campaigns and student messages.
     *
     * @return Collection<int, WhatsAppTemplate>
     */
    public function selectableTemplates(): Collection
    {
        return $this->baseQuery()->get();
    }

    /**
     * @return array<int, string>
     */
    public function selectableTemplateOptions(): array
    {
        return $this->selectableTemplates()
            ->mapWithKeys(fn (WhatsAppTemplate $template): array => [
                $template->id => $template->name.' ('.(int) $template->param_count.' param'
                    .((int) $template->param_count === 1 ? '' : 's').')',
            ])
            ->all();
    }

    public function findSelectableTemplate(int $id): ?WhatsAppTemplate
    {
        return $this->baseQuery()->whereKey($id)->first();
    }

    /**
     * @return list<string>
     */
    public function metaReadyTemplateNames(): array
    {
        return MetaWhatsAppTemplate::query()
            ->where('is_active', true)
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function orphanedPalTemplateNames(): array
    {
        if (! $this->resolver->metaOverridesPalDigital()) {
            return [];
        }

        $metaNames = $this->metaReadyTemplateNames();

        return WhatsAppTemplate::query()
            ->where('is_active', true)
            ->whereNotIn('name', $metaNames)
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    protected function baseQuery(): Builder
    {
        $query = WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($this->resolver->metaOverridesPalDigital()) {
            $metaNames = $this->metaReadyTemplateNames();

            if ($metaNames === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('name', $metaNames);
            }
        }

        return $query;
    }
}
