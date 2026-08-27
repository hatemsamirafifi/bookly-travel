<?php

namespace App\Domains\Blog\Services;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PreviewTokenService
{
    /**
     * Generate an HMAC preview token valid for 30 minutes.
     *
     * @return array{token: string, expires_at: string}
     */
    public function generate(string $slug): array
    {
        $expiresAt = Carbon::now()->addMinutes(30)->timestamp;
        $key = $this->resolveKey();

        $payload = "{$slug}|{$expiresAt}";
        $signature = hash_hmac('sha256', $payload, $key);

        $token = base64_encode("{$payload}|{$signature}");

        return [
            'token' => $token,
            'expires_at' => Carbon::createFromTimestamp($expiresAt)->toIso8601String(),
        ];
    }

    /**
     * Verify a preview token against the requested slug.
     *
     * @throws AccessDeniedHttpException on any validation failure
     */
    public function verify(string $slug, string $token): void
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            throw new AccessDeniedHttpException('Invalid preview token signature.');
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            throw new AccessDeniedHttpException('Invalid preview token structure.');
        }

        [$tokenSlug, $expiresAt, $signature] = $parts;

        // Anti-slug-rebinding: token slug must match requested slug
        if (! hash_equals($tokenSlug, $slug)) {
            throw new AccessDeniedHttpException('Preview token does not match requested article.');
        }

        // Verify signature
        $key = $this->resolveKey();
        $expectedSignature = hash_hmac('sha256', "{$tokenSlug}|{$expiresAt}", $key);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new AccessDeniedHttpException('Preview token signature tampering detected.');
        }

        // CR-003: Validate expiry is numeric before casting
        if (! ctype_digit($expiresAt)) {
            throw new AccessDeniedHttpException('Invalid preview token structure.');
        }

        // Verify expiration
        if (Carbon::now()->timestamp > (int) $expiresAt) {
            throw new AccessDeniedHttpException('Preview token has expired.');
        }
    }

    /**
     * Resolve the HMAC key, preferring a dedicated preview_key over APP_KEY.
     */
    private function resolveKey(): string
    {
        return (string) (config('app.preview_key') ?: config('app.key'));
    }
}
