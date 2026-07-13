<?php

namespace App\Support;

class WhatsAppTemplateParams
{
    /**
     * Pad to the template slot count and replace blanks so providers do not drop trailing params.
     *
     * @param  list<string>  $templateParams
     * @return list<string>
     */
    public static function normalize(array $templateParams, int $expectedParamCount = 0): array
    {
        $params = array_values($templateParams);

        if ($expectedParamCount > 0) {
            $params = array_slice($params, 0, $expectedParamCount);
            $params = array_pad($params, $expectedParamCount, '');
        }

        return array_map(
            fn (string $value): string => trim($value) === '' ? '—' : $value,
            $params,
        );
    }
}
