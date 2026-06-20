<?php

namespace App\Domains\Partner\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController
{
    public function signedUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_type' => 'required|string|in:image/jpeg,image/png',
            'file_size' => 'required|integer|max:5242880',
        ]);

        $fileType = $validated['file_type'];
        $extension = match ($fileType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        };

        $uuid = Str::uuid()->toString();
        $path = "uploads/{$uuid}.{$extension}";
        $expiresAt = now()->addMinutes(15);

        // Mock presigned URL generator — will be wired to R2 in production
        $signedUrl = "https://r2.bookly.test/{$path}?sig=" . Str::random(32) . '&expires=' . $expiresAt->getTimestamp();
        $publicUrl = "https://cdn.bookly.test/{$path}";

        return response()->json([
            'signed_url' => $signedUrl,
            'public_url' => $publicUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }
}
