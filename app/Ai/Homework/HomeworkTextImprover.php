<?php

namespace App\Ai\Homework;

interface HomeworkTextImprover
{
    public function providerName(): string;

    public function isConfigured(): bool;

    public function improve(string $title, string $description): HomeworkImprovement;
}
