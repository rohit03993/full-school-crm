<?php

namespace App\Services;

use App\Enums\WhatsAppPricingCategory;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;

class MetaWhatsAppCostEstimator
{
    public function estimateForTemplate(string $templateName, ?string $language = null): array
    {
        $category = $this->templateCategory($templateName, $language);

        return $this->estimateForCategory($category);
    }

    public function estimateForSessionReply(): array
    {
        return $this->estimateForCategory(WhatsAppPricingCategory::Service);
    }

    public function estimateForSessionMedia(): array
    {
        return $this->estimateForCategory(WhatsAppPricingCategory::Service);
    }

    /**
     * @return array{category: string, category_label: string, cost_inr: float, currency: string}
     */
    public function estimateForCategory(WhatsAppPricingCategory $category): array
    {
        $rates = $this->ratesInr();
        $key = $category->value;
        $cost = (float) ($rates[$key] ?? $rates['UNKNOWN'] ?? 0);

        return [
            'category' => $key,
            'category_label' => $category->label(),
            'cost_inr' => round($cost, 4),
            'currency' => $this->currency(),
        ];
    }

    public function templateCategory(string $templateName, ?string $language = null): WhatsAppPricingCategory
    {
        $query = MetaWhatsAppTemplate::query()
            ->where('name', $templateName)
            ->where('is_active', true);

        if (filled($language)) {
            $template = (clone $query)->where('language', $language)->first();

            if ($template) {
                return WhatsAppPricingCategory::tryFromMeta(
                    data_get($template->provider_meta, 'category'),
                );
            }
        }

        $template = $query->orderByDesc('synced_at')->first();

        if (! $template) {
            return WhatsAppPricingCategory::Unknown;
        }

        return WhatsAppPricingCategory::tryFromMeta(
            data_get($template->provider_meta, 'category'),
        );
    }

    /**
     * @return array<string, float>
     */
    public function ratesInr(): array
    {
        $defaults = $this->defaultRatesInr();
        $stored = Setting::getValue('meta_whatsapp.pricing_rates');

        if (! is_string($stored) || trim($stored) === '') {
            return $defaults;
        }

        $decoded = json_decode($stored, true);

        if (! is_array($decoded)) {
            return $defaults;
        }

        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_numeric($value)) {
                $defaults[$key] = (float) $value;
            }
        }

        return $defaults;
    }

    public function currency(): string
    {
        return (string) Setting::getValue(
            'meta_whatsapp.pricing_currency',
            config('meta_whatsapp.pricing_currency', 'INR'),
        );
    }

    /**
     * @return array<string, float>
     */
    protected function defaultRatesInr(): array
    {
        $rates = config('meta_whatsapp.pricing_rates_inr', []);

        if (! is_array($rates) || $rates === []) {
            return [
                'MARKETING' => 0.7846,
                'UTILITY' => 0.35,
                'AUTHENTICATION' => 0.35,
                'AUTHENTICATION_INTERNATIONAL' => 2.3,
                'SERVICE' => 0.0,
                'UNKNOWN' => 0.35,
            ];
        }

        return array_map('floatval', $rates);
    }
}
