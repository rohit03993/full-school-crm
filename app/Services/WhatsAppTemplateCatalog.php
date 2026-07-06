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
        return $this->baseQuery()
            ->get()
            ->map(function (WhatsAppTemplate $template): WhatsAppTemplate {
                $meta = MetaWhatsAppTemplate::query()
                    ->where('name', $template->name)
                    ->where('is_active', true)
                    ->orderByDesc('synced_at')
                    ->first();

                if ($meta) {
                    $template->setAttribute('param_count', max((int) $template->param_count, (int) $meta->param_count));
                    $template->setAttribute('body', $meta->body ?? $template->body);
                    $template->setAttribute('provider_meta', array_merge(
                        $template->provider_meta ?? [],
                        $meta->provider_meta ?? [],
                        ['meta_language' => $meta->language],
                    ));
                }

                return $template;
            });
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
