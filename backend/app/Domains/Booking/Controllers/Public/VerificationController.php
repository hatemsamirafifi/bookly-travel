<?php

namespace App\Domains\Booking\Controllers\Public;

use App\Domains\Booking\Actions\VerificationAction;
use App\Domains\Booking\Transformers\VerificationTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Spec 014 (FR-022..FR-028, SC-010/011): thin single-action controller for
 * the public voucher verification surface. It only validates the reference
 * shape (delegated to VerificationAction), then serializes the result via
 * VerificationTransformer. All status-mapping and PII-stripping logic lives
 * in the action — never duplicated here.
 *
 * GET /api/public/v/{reference} — unauthenticated, throttle:verify, no-store.
 */
class VerificationController extends Controller
{
    public function __construct(
        private readonly VerificationAction $action,
        private readonly VerificationTransformer $transformer,
    ) {}

    public function show(string $reference): JsonResponse
    {
        $result = $this->action->execute($reference);

        // Malformed and unknown references are indistinguishable to the caller
        // (SC-010/011): both 404 with the same body, no enumeration signal.
        if ($result === null) {
            return response()->json(
                ['message' => 'Not found.'],
                404,
                ['Cache-Control' => 'no-store'],
            );
        }

        return response()->json(
            $this->transformer->transform($result),
            200,
            ['Cache-Control' => 'no-store'],
        );
    }
}
