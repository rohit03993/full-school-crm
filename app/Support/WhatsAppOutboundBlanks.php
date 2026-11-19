<?php

namespace App\Support;

use App\Models\MetaWhatsAppTemplate;

class WhatsAppOutboundBlanks
{
    /**
     * Approved templates with no photo, video, or document header.
     *
     * @return list<array{id: int, label: string, blanks: list<string>}>
     */
    public static function templates(): array
    {
        return MetaWhatsAppTemplate::query()
            ->where('status', 'APPROVED')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->reject(fn (MetaWhatsAppTemplate $template): bool => self::needsFile($template))
            ->map(function (MetaWhatsAppTemplate $template): array {
                $parsed = MetaWhatsAppTemplateParser::parse(is_array($template->components) ? $template->components : []);

                return [
                    'id' => $template->id,
                    'label' => $template->name,
                    'blanks' => $parsed['body_variables'],
                ];
            })
            ->values()
            ->all();
    }

    public static function needsFile(MetaWhatsAppTemplate $template): bool
    {
        foreach ($template->components ?? [] as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));
            $format = strtoupper((string) ($component['format'] ?? ''));

            if ($type === 'HEADER' && in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $blanks
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $texts
     */
    public static function textsAreFilled(array $blanks, array $sources, array $texts): bool
    {
        foreach (array_keys($blanks) as $index) {
            if (($sources[$index] ?? 'name') === 'text' && trim((string) ($texts[$index] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $blanks
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $texts
     * @return list<string>
     */
    public static function values(array $blanks, array $sources, array $texts, string $personName): array
    {
        $name = trim($personName);
        $values = [];

        foreach (array_keys($blanks) as $index) {
            if (($sources[$index] ?? 'name') === 'text') {
                $values[] = trim((string) ($texts[$index] ?? ''));

                continue;
            }

            $values[] = $name !== '' ? $name : 'there';
        }

        return $values;
    }
}
