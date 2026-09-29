<?php

namespace App\Ai\Homework;

final class MissingHomeworkTextImprover implements HomeworkTextImprover
{
    public function __construct(private string $provider) {}

    public function providerName(): string
    {
        return $this->provider;
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function improve(string $title, string $description): HomeworkImprovement
    {
        throw new \RuntimeException('Homework AI provider is not set up.');
    }
}
