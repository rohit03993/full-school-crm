<?php

namespace App\Services;

use App\Ai\Homework\HomeworkImproveOutcome;
use App\Ai\Homework\HomeworkTextImprover;
use App\Enums\LicenseFeature;
use App\Models\User;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class HomeworkAiService
{
    public function __construct(private HomeworkTextImprover $improver) {}

    public function isAvailable(): bool
    {
        if (! (bool) config('ai.homework.enabled', true)) {
            return false;
        }

        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return false;
        }

        return $this->improver->isConfigured();
    }

    public function triesPerOpen(): int
    {
        return max(1, min(10, (int) config('ai.homework.tries_per_open', 3)));
    }

    public function dailyLimit(): int
    {
        return max(1, min(100, (int) config('ai.homework.daily_limit', 10)));
    }

    /**
     * @return array{used: int, limit: int}
     */
    public function usage(User $user): array
    {
        return [
            'used' => $this->usedToday($user),
            'limit' => $this->dailyLimit(),
        ];
    }

    /**
     * @param  array{class_label?: string, subject_label?: string, school_name?: string}  $context
     */
    public function improve(User $user, string $title, string $description, array $context = []): HomeworkImproveOutcome
    {
        $used = $this->usedToday($user);
        $limit = $this->dailyLimit();

        if (! $this->isAvailable()) {
            return HomeworkImproveOutcome::failed(
                'AI improve is not set up on this school yet. You can still type and save homework.',
                $used,
                $limit,
            );
        }

        $burstKey = 'homework-ai-burst:'.$user->id;

        if (RateLimiter::tooManyAttempts($burstKey, 6)) {
            return HomeworkImproveOutcome::failed(
                'Please wait a moment, then try again.',
                $used,
                $limit,
                temporary: true,
            );
        }

        RateLimiter::hit($burstKey, 60);

        if ($used >= $limit) {
            return HomeworkImproveOutcome::failed(
                'AI improvements used today: '.$used.' / '.$limit.'. You can still type and save homework.',
                $used,
                $limit,
            );
        }

        $title = $this->clean($title, (int) config('ai.homework.title_max', 255));
        $description = $this->clean($description, (int) config('ai.homework.description_max', 4000));

        if ($title === '' && $description === '') {
            return HomeworkImproveOutcome::failed(
                'Type a title or homework details first.',
                $used,
                $limit,
            );
        }

        try {
            $improvement = $this->improver->improve($title, $description, $this->cleanContext($context));
        } catch (\Throwable $exception) {
            Log::warning('Homework AI request failed', [
                'user_id' => $user->id,
                'provider' => $this->improver->providerName(),
                'message' => $this->redact($exception->getMessage()),
            ]);

            return HomeworkImproveOutcome::failed(
                'AI service is temporarily unavailable. Please try again.',
                $used,
                $limit,
                temporary: true,
            );
        }

        $nextUsed = $used + 1;
        Cache::put($this->dailyKey($user), $nextUsed, now()->endOfDay());

        Log::info('Homework AI request completed', [
            'user_id' => $user->id,
            'provider' => $this->improver->providerName(),
            'used_today' => $nextUsed,
        ]);

        return HomeworkImproveOutcome::success(
            $improvement->title,
            $improvement->description,
            $nextUsed,
            $limit,
        );
    }

    private function usedToday(User $user): int
    {
        return (int) Cache::get($this->dailyKey($user), 0);
    }

    private function dailyKey(User $user): string
    {
        return 'homework-ai-daily:'.$user->id.':'.now()->toDateString();
    }

    /**
     * @param  array{class_label?: string, subject_label?: string, school_name?: string}  $context
     * @return array{class_label: string, subject_label: string, school_name: string}
     */
    private function cleanContext(array $context): array
    {
        return [
            'class_label' => $this->clean((string) ($context['class_label'] ?? ''), 200),
            'subject_label' => $this->clean((string) ($context['subject_label'] ?? ''), 200),
            'school_name' => $this->clean((string) ($context['school_name'] ?? ''), 200),
        ];
    }

    private function clean(string $value, int $max): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace("/[ \t]+\n/", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;
        $max = max(1, $max);

        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }

        return trim($value);
    }

    private function redact(string $text): string
    {
        foreach (['ai.gemini.key', 'ai.openai.key'] as $configKey) {
            $secret = (string) config($configKey);

            if ($secret !== '') {
                $text = str_replace($secret, '***', $text);
            }
        }

        $text = preg_replace('/key=[^&\s]+/i', 'key=***', $text) ?? $text;
        $text = preg_replace('/Bearer\s+\S+/i', 'Bearer ***', $text) ?? $text;

        return mb_substr($text, 0, 300);
    }
}
