<?php

namespace App\Domains\Partner\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class TourDraftConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $errorMessage,
        public readonly array $serverDraft = [],
    ) {
        parent::__construct($errorMessage);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->errorCode,
            'message' => $this->errorMessage,
            'server_draft' => $this->serverDraft,
        ], 409);
    }
}
