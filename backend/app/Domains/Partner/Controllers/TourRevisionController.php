<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Requests\RespondToTourChangeRequest;
use App\Domains\Partner\Requests\StoreTourRevisionRequest;
use App\Domains\Partner\Requests\UpdateTourRevisionRequest;
use App\Domains\Partner\Services\TourRevisionService;
use App\Domains\Partner\Services\TourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Owned tour revision API (ADR 0024, design v3 §6). Every handler is thin: the
 * domain service owns validation, locking, transition and audit. Only the
 * payload hash is an exposed replacement token; canonical source/content hashes
 * and provider or translation diagnostics never leave the boundary.
 */
class TourRevisionController
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly TourService $tours,
        private readonly TourRevisionService $revisions,
    ) {}

    /** GET /tours/{id}/revisions — bounded paginated history. */
    public function index(Request $request, string $id): JsonResponse
    {
        $partnerId = (int) $request->attributes->get('partner_id');
        $this->tours->requireOwnedTour((int) $id, $partnerId);
        $validated = $request->validate(['per_page' => 'sometimes|integer|min:1|max:50']);

        return $this->readResponse(fn (): array => $this->revisions->ownedRevisionHistory((int) $id, $partnerId, $validated['per_page'] ?? self::PER_PAGE));
    }

    /** GET /tours/{id}/revisions/{revisionId} — full owned payload. */
    public function show(Request $request, string $id, string $revisionId): JsonResponse
    {
        return $this->readResponse(fn (): array => ['data' => $this->revisions->ownedRevisionPayload(
            (int) $id, (int) $request->attributes->get('partner_id'), (int) $revisionId,
        )]);
    }

    /** POST /tours/{id}/revisions — 201 plus complete payload metadata. */
    public function store(StoreTourRevisionRequest $request, string $id): JsonResponse
    {
        $tourId = (int) $id;
        $actorId = (int) $request->user()->id;
        $observedBaseId = $request->observedBaseRevisionId();

        try {
            $revision = $this->revisions->createCandidate(
                $tourId,
                $actorId,
                $observedBaseId,
                $request->contentPatch(),
                $request->changeSummary(),
            );
        } catch (TourRevisionException $exception) {
            return $this->renderRefusal($exception);
        } catch (HttpExceptionInterface|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->renderUnexpected($exception);
        }

        $tour = $this->tours->requireOwnedTour($tourId, (int) $request->attributes->get('partner_id'));

        return response()->json([
            'data' => $this->revisions->ownedRevisionPayload($tourId, (int) $request->attributes->get('partner_id'), $revision->id),
            'message' => 'Content revision created.',
        ], 201);
    }

    /** PUT /tours/{id}/revisions/{revisionId} — NEW replacement id. */
    public function update(UpdateTourRevisionRequest $request, string $id, string $revisionId): JsonResponse
    {
        $tourId = (int) $id;
        $actorId = (int) $request->user()->id;

        try {
            $revision = $this->revisions->replaceCandidate(
                $tourId,
                (int) $revisionId,
                $actorId,
                $request->observedBaseRevisionId(),
                $request->observedPayloadHash(),
                $request->contentPatch(),
                $request->changeSummary(),
                $request->hasChangeSummary(),
            );
        } catch (TourRevisionException $exception) {
            return $this->renderRefusal($exception);
        } catch (HttpExceptionInterface|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->renderUnexpected($exception);
        }

        $tour = $this->tours->requireOwnedTour($tourId, (int) $request->attributes->get('partner_id'));

        return response()->json([
            'data' => $this->revisions->ownedRevisionPayload($tourId, (int) $request->attributes->get('partner_id'), $revision->id),
            'message' => 'Content revision replaced with a new draft.',
        ]);
    }

    /** POST /tours/{id}/revisions/{revisionId}/submit. */
    public function submit(Request $request, string $id, string $revisionId): JsonResponse
    {
        $tourId = (int) $id;
        $partnerId = (int) $request->attributes->get('partner_id');
        $tour = $this->tours->requireOwnedTour($tourId, $partnerId);

        try {
            $revision = $this->revisions->submitCandidate($tour, (int) $revisionId, (int) $request->user()->id);
        } catch (TourRevisionException $exception) {
            return $this->renderRefusal($exception);
        } catch (HttpExceptionInterface|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->renderUnexpected($exception);
        }

        return response()->json([
            'data' => $this->revisions->ownedRevisionPayload($tourId, (int) $request->attributes->get('partner_id'), $revision->id),
            'message' => 'Content revision submitted for review.',
        ]);
    }

    /** POST /tours/{id}/revisions/{revisionId}/withdraw. */
    public function withdraw(Request $request, string $id, string $revisionId): JsonResponse
    {
        $tourId = (int) $id;
        $partnerId = (int) $request->attributes->get('partner_id');
        $tour = $this->tours->requireOwnedTour($tourId, $partnerId);

        try {
            $revision = $this->revisions->withdrawCandidate($tour, (int) $revisionId, (int) $request->user()->id);
        } catch (TourRevisionException $exception) {
            return $this->renderRefusal($exception);
        } catch (HttpExceptionInterface|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->renderUnexpected($exception);
        }

        return response()->json([
            'data' => $this->revisions->ownedRevisionPayload($tourId, (int) $request->attributes->get('partner_id'), $revision->id),
            'message' => 'Content revision withdrawn.',
        ]);
    }

    /** GET /tours/{id}/revision-requests — bounded paginated history. */
    public function requests(Request $request, string $id): JsonResponse
    {
        $partnerId = (int) $request->attributes->get('partner_id');
        $this->tours->requireOwnedTour((int) $id, $partnerId);
        $validated = $request->validate(['per_page' => 'sometimes|integer|min:1|max:50']);

        return $this->readResponse(fn (): array => $this->revisions->ownedRequestHistory((int) $id, $partnerId, $validated['per_page'] ?? self::PER_PAGE));
    }

    /** POST /tours/{id}/revision-requests/{requestId}/respond. */
    public function respond(RespondToTourChangeRequest $request, string $id, string $requestId): JsonResponse
    {
        $partnerId = (int) $request->attributes->get('partner_id');
        $tour = $this->tours->requireOwnedTour((int) $id, $partnerId);

        try {
            $answered = $this->revisions->respondToRequest(
                $tour,
                (int) $requestId,
                $partnerId,
                (int) $request->user()->id,
                $request->responseNote(),
                [
                    'revision_id' => $request->observedRevisionId(),
                    'generation_epoch' => $request->observedGenerationEpoch(),
                ],
            );
        } catch (TourRevisionException $exception) {
            return $this->renderRefusal($exception);
        } catch (HttpExceptionInterface|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->renderUnexpected($exception);
        }

        return response()->json([
            'data' => $this->revisions->ownedRequestPayload((int) $id, $partnerId, $answered->id),
            'message' => 'Change request answered.',
        ]);
    }

    private function readResponse(callable $projection): JsonResponse
    {
        try {
            return response()->json($projection());
        } catch (TourRevisionException $exception) {
            return $this->renderRefusal($exception);
        }
    }

    private function renderRefusal(TourRevisionException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            ...$exception->contractPayload(),
        ], $exception->status);
    }

    private function renderUnexpected(Throwable $exception): JsonResponse
    {
        if ($exception instanceof HttpExceptionInterface) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Request failed.',
            ], $exception->getStatusCode());
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'The revision request could not be completed.',
        ], 500);
    }
}
