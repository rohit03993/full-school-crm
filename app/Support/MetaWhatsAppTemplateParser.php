<?php

namespace App\Support;

class MetaWhatsAppTemplateParser
{
    /**
     * @param  list<array<string, mixed>>  $components
     * @return array{body: ?string, param_count: int, body_variables: list<string>}
     */
    public static function parse(array $components): array
    {
        $body = null;
        $bodyVariables = [];

        foreach ($components as $component) {
            if (! is_array($component) || ($component['type'] ?? '') !== 'BODY') {
                continue;
            }

            $body = isset($component['text']) ? (string) $component['text'] : null;

            if ($body === null || $body === '') {
                break;
            }

            if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $namedMatches)) {
                $bodyVariables = array_values($namedMatches[1]);
            } elseif (preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $positionalMatches)) {
                $bodyVariables = array_values($positionalMatches[1]);
            }

            break;
        }

        return [
            'body' => $body,
            'param_count' => count($bodyVariables),
            'body_variables' => $bodyVariables,
        ];
    }
}
