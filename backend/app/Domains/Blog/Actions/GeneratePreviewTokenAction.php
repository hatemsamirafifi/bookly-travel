<?php

namespace App\Domains\Blog\Actions;

use App\Domains\Blog\Services\PreviewTokenService;

class GeneratePreviewTokenAction
{
    public function __construct(
        private readonly PreviewTokenService $tokenService
    ) {}

    /**
     * Generate an HMAC preview token valid for 30 minutes.
     *
     * @return array{token: string, expires_at: string}
     */
    public function execute(string $slug): array
    {
        return $this->tokenService->generate($slug);
    }
}
