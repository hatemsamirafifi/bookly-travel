<?php

namespace App\Domains\Admin\Services;

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourRevisionPayloadService;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Shared fail-closed publication-gate helpers for EGM-002 (Supplier Portal
 * Goldfish Review A4 blocker; Constitution v3.0.0 §II and §IV).
 *
 * Publication happens ONLY through ApproveTourAction. The general Filament
 * create/edit form can no longer change lifecycle state: attempted writes are
 * refused against the freshly locked persisted row instead of being silently
 * sanitized into a successful partial save.
 */
class TourPublicationGuardService
{
    /**
     * Caller holds a transaction. Lock owners before the tour, matching partner
     * suspension. Include a proposed owner so reassignment cannot race suspension.
     */
    public function lockTourForMutation(Tour $tour, mixed $requestedOwnerId = null): Tour
    {
        $snapshot = Tour::findOrFail($tour->getKey());
        $ownerIds = [$snapshot->partner_id];
        $requestedOwner = filter_var($requestedOwnerId, FILTER_VALIDATE_INT);
        if ($requestedOwner !== false && $requestedOwner > 0) {
            $ownerIds[] = $requestedOwner;
        }
        Partner::query()->whereIn('id', $ownerIds)->orderBy('id')->lockForUpdate()->get();
        $locked = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
        abort_unless($locked->partner_id === $snapshot->partner_id, 409, 'Tour ownership changed. Reload before trying again.');

        return $locked;
    }

    /**
     * Authorize the PASSED actor explicitly against the registered `publish`
     * policy (AppServiceProvider: Gate::policy(Tour::class, TourPolicy::class)),
     * re-resolving the actor from persisted permission flags and account
     * status so a revoke or suspension applies immediately instead of
     * trusting a stale in-memory model instance.
     *
     * @return User the freshly resolved, authorized actor
     */
    public function resolveAuthorizedPublisher(User $actor, Tour $tour): User
    {
        $freshActor = $actor->fresh();

        if ($freshActor === null || ! $freshActor->can('publish', $tour)) {
            throw new AuthorizationException('You are not authorized to publish tours.');
        }

        return $freshActor;
    }

    /**
     * EGM-003 owner policy: tour creation is a partner operation. The general
     * administration interface does not create tours, so no valid submitted
     * payload is accepted regardless of its lifecycle value.
     *
     * @param  array<string, mixed>  $rawData
     */
    public function refuseGeneralTourCreation(array $rawData = []): void
    {
        $errors = [
            'data.status' => 'Tours are created in the partner portal. The administration interface reviews and requests changes on partner-authored content.',
        ];

        if (array_key_exists('translations', $rawData)) {
            $errors['data.translations'] = 'Canonical tour content is never authored in this form. Partners own and edit canonical content.';
        }

        throw ValidationException::withMessages($errors);
    }

    /**
     * General create form: only ever materializes a draft. Any submitted
     * lifecycle value other than `draft` is an attempted write and is refused.
     */
    public function assertGeneralCreateRequiresDraft(?string $submittedStatus): void
    {
        if ($submittedStatus !== null && $submittedStatus !== TourStatus::Draft->value) {
            throw ValidationException::withMessages([
                'data.status' => 'Tours are created as drafts. Publishing is only available through the approval workflow.',
            ]);
        }
    }

    /**
     * General edit form: the submitted raw lifecycle status must equal the
     * freshly locked persisted status. This prevents a stale published form
     * from republishing a concurrently-unpublished record and a stale pending
     * form from silently retracting a published one; a valid existing
     * published metadata save stays published with its stamp and audit
     * unchanged.
     */
    public function assertGeneralSaveKeepsCurrentStatus(?string $submittedStatus, Tour $lockedTour): void
    {
        if ($submittedStatus !== null && $submittedStatus !== $lockedTour->status) {
            throw ValidationException::withMessages([
                'data.status' => sprintf(
                    'This tour is currently "%s". Lifecycle changes through this form are rejected; publish and unpublish remain governed actions with their own audit trail.',
                    $lockedTour->status,
                ),
            ]);
        }
    }

    /**
     * EGM-002 published-edit integrity, retained (design v3 §4: existing EGM-002
     * guards are retained). After the main record and relationship rows are
     * saved, a still-published tour must keep a nonblank canonical source title
     * and description in its immutable source locale and an approved owner.
     * Canonical source writes are refused at the form boundary under EGM-003;
     * this remains the last atomic defense for the ownership path.
     */
    public function assertPublishedTourEditIntegrity(Tour $savedTour): void
    {
        if ($savedTour->status !== TourStatus::Published->value) {
            return;
        }

        $source = $savedTour->sourceTranslation();

        if ($source === null || ! filled($source->title) || ! filled($source->description)) {
            throw ValidationException::withMessages([
                'data.translations' => sprintf(
                    'A published tour requires a title and description in its source language (%s).',
                    $savedTour->sourceLocale(),
                ),
            ]);
        }

        $owner = $savedTour->partnerRecord;

        if ($owner === null || $owner->onboarding_status !== 'approved') {
            throw ValidationException::withMessages([
                'data.partner_id' => 'A published tour must remain owned by an approved partner.',
            ]);
        }
    }

    /**
     * EGM-003 general-form content fence (ADR 0024): compare the RAW submitted
     * state against the freshly locked persisted row before any relationship
     * persistence, so a read-only control alone can never be trusted and a
     * forged Livewire payload cannot author canonical source text, a generated
     * derivative, or a governed presentation field.
     *
     * Read-only controls are removed from the form schema; this is the
     * enforcement. Any `translations` key at all is a tamper attempt (the form
     * renders no translation control), and any governed presentation field
     * whose raw value differs from the stored value is refused at its own path.
     *
     * @param  array<string, mixed>  $rawData  the unfiltered Livewire form data
     * @param  array<string, string>|null  $governedValues  persisted governed values
     */
    public function assertGeneralFormRefusesContentWrites(array $rawData, ?array $governedValues = null): void
    {
        $errors = [];

        if (array_key_exists('translations', $rawData)) {
            $errors['data.translations'] = 'Canonical tour content and generated derivatives are never editable in this form. Partners own and edit canonical content, and generated translations are never manually edited.';
        }

        if ($governedValues !== null) {
            foreach (TourRevisionPayloadService::PRESENTATION_FIELDS as $field) {
                $path = "data.{$field}";

                if (! array_key_exists($field, $rawData) || ! array_key_exists($field, $governedValues)) {
                    continue;
                }

                if (! $this->valueSurvivesRoundTrip($rawData[$field], $governedValues[$field])) {
                    $errors[$path] = sprintf(
                        '"%s" is a governed field owned by the partner; request a change through the review workflow instead of editing it here.',
                        $field,
                    );
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The persisted governed presentation values this form must round-trip
     * unchanged, keyed by form field name.
     *
     * @return array<string, mixed>
     */
    public function governedPresentationValues(Tour $locked): array
    {
        $values = [];

        foreach (TourRevisionPayloadService::PRESENTATION_FIELDS as $field) {
            if ($field === 'guide_languages') {
                $values[$field] = $locked->guide_languages;

                continue;
            }

            $values[$field] = $locked->getAttribute($field);
        }

        return $values;
    }

    /**
     * Filament stores the mounted value with its own casts; a round-tripped
     * unchanged value must not be refused. Integers may arrive as strings and
     * booleans may arrive as 0/1.
     */
    private function valueSurvivesRoundTrip(mixed $submitted, mixed $stored): bool
    {
        if ($submitted === $stored) {
            return true;
        }

        if ($stored === null) {
            return $submitted === null || $submitted === '' || $submitted === false || $submitted === [];
        }

        if (is_bool($stored)) {
            return filter_var($submitted, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === $stored;
        }

        if (is_int($stored)) {
            return filter_var($submitted, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) === $stored;
        }

        if (is_array($stored)) {
            $submittedList = is_array($submitted) ? $submitted : null;

            if ($submittedList === null) {
                return false;
            }

            $normalize = static fn (mixed $value): array => array_values(array_map(
                static fn (mixed $item): string => trim((string) $item),
                array_filter($value, fn (mixed $item): bool => $item !== null),
            ));

            $submittedList = $normalize($submittedList);
            sort($submittedList);
            $storedList = $normalize($stored);
            sort($storedList);

            return $submittedList === $storedList;
        }

        if (is_numeric($stored)) {
            return filter_var($submitted, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) === (float) $stored;
        }

        if (is_string($stored) && $stored !== '') {
            return is_scalar($submitted) && trim((string) $submitted) === trim($stored);
        }

        return false;
    }
}
