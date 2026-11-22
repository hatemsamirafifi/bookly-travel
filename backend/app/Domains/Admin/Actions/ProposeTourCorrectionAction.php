<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Requests\TourContentRules;
use App\Domains\Partner\Services\TourRevisionService;
use App\Models\Tour;
use App\Models\TourContentRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Exceptional administrative correction (ADR 0024 rule 5, design v3 §5).
 *
 * Requires the explicit `correct_tour_content` permission: the general Filament
 * form can never perform it, and no ordinary administrator default or role
 * template grants the permission. The correction supplies a mandatory reason,
 * declared canonical source fields and a validated patch; the revision service
 * builds ONE immutable proposed revision (kind admin_correction) plus an open
 * correction request, audited inside the mutation transaction. It never writes
 * the live source and never publishes. Ordinary review remains mandatory and
 * the existing EGM-002 approval guard stays authoritative.
 */
class ProposeTourCorrectionAction
{
    public function __construct(
        private readonly TourRevisionService $revisions,
        private readonly AdminAuthorizationService $authorization,
    ) {}

    /**
     * @param  array<string, mixed>  $data  reason, field_refs, source_* values
     */
    public function execute(User $actor, Tour|int $tour, array $data): TourContentRevision
    {
        $freshActor = $actor->fresh();

        if ($freshActor === null || ! $this->authorization->can($freshActor, 'correct_tour_content')) {
            throw new AuthorizationException('You are not authorized to propose exceptional corrections.');
        }

        $reason = is_string($data['reason'] ?? null) ? trim((string) $data['reason']) : '';

        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'A reason of 1-2000 characters is required.',
            ]);
        }

        $refs = TourContentRules::normalizeItineraryFieldReferences($data['field_refs'] ?? null);

        // Canonical-source-only correction patch in this slice: only the nine
        // canonical source fields may change; every other governed field uses a
        // partner request instead.
        $patch = ['source' => []];
        $nestedSource = is_array($data['source'] ?? null) ? $data['source'] : [];

        foreach (TourContentRules::SOURCE_FIELDS as $field) {
            $key = "source_{$field}";

            if (array_key_exists($field, $nestedSource)) {
                $value = $nestedSource[$field];

                if ($field === 'title' && (! is_string($value) || trim($value) === '')) {
                    continue;
                }

                $patch['source'][$field] = $value;

                continue;
            }

            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if ($field === 'title' && (! is_string($value) || trim($value) === '')) {
                continue;
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (in_array($field, ['highlights', 'inclusions', 'exclusions', 'important_information'], true)) {
                $parsed = $this->normalizeListInput($value, $key);
                if ($parsed === []) {
                    continue;
                }
                $patch['source'][$field] = $parsed;

                continue;
            }

            if ($field === 'itinerary') {
                $patch['source']['itinerary'] = $this->normalizeItineraryInput($value);

                continue;
            }

            $patch['source'][$field] = $value;
        }

        if ($patch['source'] === []) {
            throw ValidationException::withMessages([
                'source_title' => 'A correction must change at least one canonical source field.',
            ]);
        }

        try {
            return $this->revisions->proposeCorrection($tour, $freshActor, $reason, $refs, $patch);
        } catch (TourRevisionException $exception) {
            throw RequestTourChangesAction::toValidationException($exception);
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeListInput(mixed $value, string $fieldKey): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $fieldKey => 'Must be a list of lines or a JSON array of strings.',
            ]);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw ValidationException::withMessages([
                    $fieldKey => 'Invalid JSON list for canonical correction field.',
                ]);
            }

            return array_values($decoded);
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $trimmed) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @return list<mixed>
     */
    private function normalizeItineraryInput(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                'source_itinerary' => 'Itinerary correction must be a valid JSON list of days.',
            ]);
        }

        $decoded = json_decode(trim($value), true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || ! array_is_list($decoded)) {
            throw ValidationException::withMessages([
                'source_itinerary' => 'Itinerary correction must be a valid JSON list of days.',
            ]);
        }

        return $decoded;
    }
}
