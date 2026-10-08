<?php

namespace App\Ai\Homework;

interface HomeworkTextImprover
{
    public function providerName(): string;

    public function isConfigured(): bool;

    /**
     * @param  array{class_label?: string, subject_label?: string, school_name?: string, teacher_name?: string}  $context
     */
    public function improve(string $title, string $description, array $context = []): HomeworkImprovement;
}
