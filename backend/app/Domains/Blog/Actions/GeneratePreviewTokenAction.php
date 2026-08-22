<?php

namespace App\Domains\Blog\Actions;

use Illuminate\Support\Carbon;

class GeneratePreviewTokenAction
{
    /**
     * Generate an HMAC preview token valid for 30 minutes.
     *
     * @param string $slug
     * @return array{token: string, expires_at: string}
     */
    public function execute(string $slug): array
    {
        $expiresAt = Carbon::now()->addMinutes(30)->timestamp;
        $key = config('app.preview_key') ?: config('app.key');
        
        $payload = "{$slug}|{$expiresAt}";
        $signature = hash_hmac('sha256', $payload, (string) $key);
        
        $token = base64_encode("{$payload}|{$signature}");

        return [
            'token' => $token,
            'expires_at' => Carbon::createFromTimestamp($expiresAt)->toIso8601String(),
        ];
    }
}
