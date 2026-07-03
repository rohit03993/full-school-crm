<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Services\PwaManifestService;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    public function __invoke(string $context): JsonResponse
    {
        if (! in_array($context, ['public', 'portal', 'admin'], true)) {
            abort(404);
        }

        return response()->json(
            PwaManifestService::manifest($context),
            200,
            ['Content-Type' => 'application/manifest+json'],
            JSON_UNESCAPED_SLASHES,
        );
    }
}
