<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppPricingAnalyticsService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     error?: string,
     *     currency?: string,
     *     total_cost?: float,
     *     total_volume?: int,
     *     by_category?: array<string, array{volume: int, cost: float}>,
     *     data_points?: list<array<string, mixed>>
     * }
     */
    public function fetchForRange(\DateTimeInterface $from, \DateTimeInterface $to, string $granularity = 'DAILY'): array
    {
        if (! $this->meta->isConfigured()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not configured.'];
        }

        $wabaId = $this->meta->wabaId();

        if (blank($wabaId)) {
            return ['status' => 'failed', 'error' => 'WhatsApp Business Account ID (WABA) is required for Meta pricing analytics.'];
        }

        $start = $from->getTimestamp();
        $end = $to->getTimestamp();

        if ($end <= $start) {
            return ['status' => 'failed', 'error' => 'End date must be after start date.'];
        }

        $fields = sprintf(
            'pricing_analytics.start(%d).end(%d).granularity(%s).metric_types(COST,VOLUME).dimensions(PRICING_CATEGORY,PRICING_TYPE).country_codes(IN)',
            $start,
            $end,
            strtoupper($granularity),
        );

        try {
            $response = Http::timeout(45)
                ->withToken((string) $this->meta->accessToken())
                ->acceptJson()
                ->get($this->meta->graphUrl($wabaId), [
                    'fields' => $fields,
                ]);

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return [
                    'status' => 'failed',
                    'error' => $this->meta->parseApiError($data, $response->body()),
                ];
            }

            return $this->parsePricingAnalytics($data);
        } catch (\Throwable $exception) {
            Log::warning('Meta WhatsApp pricing analytics failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     status: string,
     *     currency: string,
     *     total_cost: float,
     *     total_volume: int,
     *     by_category: array<string, array{volume: int, cost: float}>,
     *     data_points: list<array<string, mixed>>
     * }
     */
    protected function parsePricingAnalytics(array $payload): array
    {
        $points = data_get($payload, 'pricing_analytics.data.0.data_points', []);

        if (! is_array($points)) {
            $points = [];
        }

        $byCategory = [];
        $totalCost = 0.0;
        $totalVolume = 0;

        foreach ($points as $point) {
            if (! is_array($point)) {
                continue;
            }

            $category = strtoupper((string) ($point['pricing_category'] ?? 'UNKNOWN'));
            $volume = (int) ($point['volume'] ?? 0);
            $cost = (float) ($point['cost'] ?? 0);

            if (! isset($byCategory[$category])) {
                $byCategory[$category] = ['volume' => 0, 'cost' => 0.0];
            }

            $byCategory[$category]['volume'] += $volume;
            $byCategory[$category]['cost'] += $cost;
            $totalVolume += $volume;
            $totalCost += $cost;
        }

        return [
            'status' => 'success',
            'currency' => 'INR',
            'total_cost' => round($totalCost, 4),
            'total_volume' => $totalVolume,
            'by_category' => $byCategory,
            'data_points' => array_values($points),
        ];
    }
}
