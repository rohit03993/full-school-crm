<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Services\PwaManifestService;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    /**
     * Serve the single institute manifest.
     *
     * Legacy /pwa/manifest/{admin|portal|public} URLs still work and return
     * the same payload so already-linked pages do not break.
     */
    public function __invoke(?string $context = null): JsonResponse
    {
        if ($context !== null && ! in_array($context, ['public', 'portal', 'admin', 'app'], true)) {
            abort(404);
        }

        return response()->json(
            PwaManifestService::manifest(),
            200,
            [
                'Content-Type' => 'application/manifest+json',
                // Branding can change at any time; never let a stale name or
                // icon set stick to an installed app.
                'Cache-Control' => 'no-cache, must-revalidate',
            ],
            JSON_UNESCAPED_SLASHES,
        );
    }
}
