<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait AuthenticatesFaceVerifyCallbacks
{
    protected function bearerTokenMatches(Request $request): bool
    {
        $expected = (string) config('face_verify.service_token');
        $provided = (string) $request->bearerToken();

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    protected function signatureMatches(string $rawBody, string $signature): bool
    {
        $secret = (string) config('face_verify.callback_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
