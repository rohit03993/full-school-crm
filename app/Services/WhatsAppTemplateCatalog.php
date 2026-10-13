<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateParser;
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
        $templates = $this->baseQuery()->get();

        if ($templates->isEmpty()) {
            return $templates;
        }

        $metasByName = MetaWhatsAppTemplate::query()
            ->whereIn('name', $templates->pluck('name')->all())
            ->where('is_active', true)
            ->orderByDesc('synced_at')
            ->get()
            ->unique('name')
            ->keyBy('name');

        return $templates->map(function (WhatsAppTemplate $template) use ($metasByName): WhatsAppTemplate {
            $meta = $metasByName->get($template->name);

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

    public function headerFormatForName(string $name): ?string
    {
        $meta = MetaWhatsAppTemplate::query()
            ->where('name', $name)
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereRaw('UPPER(status) = ?', ['APPROVED']);
            })
            ->orderByDesc('synced_at')
            ->first();

        if (! $meta) {
            return null;
        }

        $stored = strtoupper(trim((string) data_get($meta->provider_meta, 'header_format', '')));

        if (in_array($stored, ['IMAGE', 'VIDEO', 'DOCUMENT', 'TEXT'], true)) {
            return $stored;
        }

        return MetaWhatsAppTemplateParser::headerFormat(is_array($meta->components) ? $meta->components : []);
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
    public function orphanedLocalTemplateNames(): array
    {
        if (! $this->resolver->isMetaActive()) {
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

    /** @deprecated Use orphanedLocalTemplateNames() */
    public function orphanedPalTemplateNames(): array
    {
        return $this->orphanedLocalTemplateNames();
    }

    protected function baseQuery(): Builder
    {
        $query = WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($this->resolver->isMetaActive()) {
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
