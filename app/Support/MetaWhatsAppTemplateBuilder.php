<?php

namespace App\Support;

use InvalidArgumentException;

class MetaWhatsAppTemplateBuilder
{
    /**
     * Build Meta message_templates POST body from CRM form fields.
     *
     * @return array{
     *     name: string,
     *     language: string,
     *     category: string,
     *     components: list<array<string, mixed>>,
     *     parameter_format?: string,
     *     allow_category_change: bool
     * }
     */
    public static function buildCreatePayload(
        string $name,
        string $language,
        string $category,
        string $bodyText,
        ?string $headerText = null,
        ?string $footerText = null,
        ?string $bodyExamplesCsv = null,
        bool $allowCategoryChange = true,
    ): array {
        $name = self::normalizeName($name);
        $language = trim($language);
        $category = strtoupper(trim($category));
        $bodyText = trim($bodyText);

        if ($name === '' || strlen($name) < 3) {
            throw new InvalidArgumentException('Template name must be at least 3 characters (lowercase letters, numbers, underscores).');
        }

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Template name must use lowercase letters, numbers, and underscores only.');
        }

        if ($bodyText === '') {
            throw new InvalidArgumentException('Message body is required.');
        }

        if ($headerText !== null && str_contains($headerText, '{{')) {
            throw new InvalidArgumentException('Header cannot include variables. Use plain text only.');
        }

        if ($footerText !== null && str_contains($footerText, '{{')) {
            throw new InvalidArgumentException('Footer cannot include variables.');
        }

        $components = [];

        if (filled($headerText)) {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => trim($headerText),
            ];
        }

        [$bodyComponent, $parameterFormat] = self::buildBodyComponent($bodyText, $bodyExamplesCsv);
        $components[] = $bodyComponent;

        if (filled($footerText)) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => trim($footerText),
            ];
        }

        $payload = [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => $components,
            'allow_category_change' => $allowCategoryChange,
        ];

        if ($parameterFormat !== null) {
            $payload['parameter_format'] = $parameterFormat;
        }

        return $payload;
    }

    /**
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    protected static function buildBodyComponent(string $bodyText, ?string $bodyExamplesCsv): array
    {
        $indices = self::positionalPlaceholderOrder($bodyText);

        if ($indices === []) {
            return [['type' => 'BODY', 'text' => $bodyText], null];
        }

        if (preg_match('/\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}/', $bodyText)) {
            throw new InvalidArgumentException('Use positional placeholders like {{1}}, {{2}} — not named variables.');
        }

        $examples = self::parseExamplesCsv($bodyExamplesCsv);

        if (count($examples) < count($indices)) {
            throw new InvalidArgumentException(
                'The body has '.count($indices).' variable(s). Provide that many comma-separated sample values.'
            );
        }

        return [
            [
                'type' => 'BODY',
                'text' => $bodyText,
                'example' => [
                    'body_text' => [array_slice($examples, 0, count($indices))],
                ],
            ],
            'positional',
        ];
    }

    /**
     * @return list<int>
     */
    public static function positionalPlaceholderOrder(string $bodyText): array
    {
        if (! preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyText, $matches)) {
            return [];
        }

        $seen = [];
        $order = [];

        foreach ($matches[1] as $index) {
            $n = (int) $index;

            if (! in_array($n, $seen, true)) {
                $seen[] = $n;
                $order[] = $n;
            }
        }

        return $order;
    }

    /**
     * @return list<string>
     */
    public static function parseExamplesCsv(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $csv)),
            fn (string $value): bool => $value !== '',
        ));
    }

    public static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', '_', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9_]/', '', $name) ?? $name;

        return $name;
    }
}
