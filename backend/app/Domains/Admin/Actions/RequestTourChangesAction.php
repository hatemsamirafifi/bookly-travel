<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Requests\TourContentRules;
use App\Domains\Partner\Services\TourRevisionService;
use App\Models\Tour;
use App\Models\TourRevisionRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Administrator change request on partner content (ADR 0024 rule 2; design v3 §5).
 *
 * Mandatory reason (1–2000) plus 1–30 resolvable field references. The request
 * never writes content and never approves anything. `correction` context
 * requests accompany an exceptional correction and stay open for ordinary
 * review.
 */
class RequestTourChangesAction
{
    public function __construct(
        private readonly TourRevisionService $revisions,
        private readonly AdminAuthorizationService $authorization,
    ) {}

    /**
     * @param  array<string, mixed>  $data  context, reason, field_refs
     */
    public function execute(User $actor, Tour|int $tour, array $data): TourRevisionRequest
    {
        $freshActor = $actor->fresh();

        if ($freshActor === null || ! $this->authorization->can($freshActor, 'manage_tours')) {
            throw new AuthorizationException('You are not authorized to request content changes.');
        }

        $reason = is_string($data['reason'] ?? null) ? trim((string) $data['reason']) : '';

        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'A reason of 1-2000 characters is required.',
            ]);
        }

        $context = is_string($data['context'] ?? null) ? $data['context'] : TourRevisionRequest::CONTEXT_PUBLISHED_REVIEW;

        if (! in_array($context, TourRevisionRequest::CONTEXTS, true)) {
            throw ValidationException::withMessages([
                'context' => 'Unsupported request type.',
            ]);
        }

        $refs = TourContentRules::normalizeItineraryFieldReferences($data['field_refs'] ?? null);

        $request = $this->revisions->requestChanges($tour, $freshActor, $context, $reason, $refs);

        return $request;
    }

    /**
     * Surface a domain refusal through the Filament form with its own error
     * paths; the safe public message is used and no internals leak.
     */
    public static function toValidationException(TourRevisionException $exception): ValidationException
    {
        $errors = [];

        foreach ($exception->paths as $path => $message) {
            $errors["data.{$path}"] = $message;
        }

        if ($errors === []) {
            $errors['data.reason'] = $exception->getMessage();
        }

        return ValidationException::withMessages($errors);
    }
}
