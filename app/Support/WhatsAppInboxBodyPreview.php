<?php

namespace App\Support;

/**
 * CRM stores a copy of each WhatsApp for the inbox bubble.
 * Meta still delivers the full text to the phone.
 */
final class WhatsAppInboxBodyPreview
{
    /** TEXT column can hold much more; this only stops huge inbound spam. */
    public const MAX_LENGTH = 8000;

    public static function clip(string $preview): string
    {
        return mb_substr($preview, 0, self::MAX_LENGTH);
    }

    /**
     * Old rows were cut at 500 letters (homework links often hit that).
     * If the saved copy stops inside the last fixed sentence of the template, finish that sentence.
     */
    public static function appendMissingTemplateSuffix(string $preview, string $templateBody): string
    {
        if ($preview === '' || $templateBody === '') {
            return $preview;
        }

        if (! preg_match('/\{\{\s*\d+\s*\}\}(?!.*\{\{\s*\d+\s*\}\})(.*)\z/s', $templateBody, $matches)) {
            return $preview;
        }

        $suffix = $matches[1];

        if ($suffix === '') {
            return $preview;
        }

        $visibleSuffix = ltrim($suffix);

        if ($visibleSuffix !== '' && str_contains($preview, $visibleSuffix)) {
            return $preview;
        }

        foreach ([$suffix, $visibleSuffix] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $candidateLength = mb_strlen($candidate);

            for ($keep = $candidateLength - 1; $keep >= 8; $keep--) {
                $prefix = mb_substr($candidate, 0, $keep);

                if (str_ends_with($preview, $prefix)) {
                    return $preview.mb_substr($candidate, $keep);
                }
            }
        }

        if (mb_strlen($preview) >= 500) {
            return $preview.$suffix;
        }

        return $preview;
    }
}
