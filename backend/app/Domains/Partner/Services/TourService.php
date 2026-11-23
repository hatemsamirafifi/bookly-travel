<?php

namespace App\Domains\Partner\Services;

use App\Domains\CMS\Services\HtmlSanitizerService;
use App\Domains\Partner\Exceptions\TourDraftConflictException;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Requests\TourContentRules;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourOption;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TourService
{
    public function __construct(private readonly TourTranslationService $translationService) {}

    public function listForPartner(int $partnerId, array $filters = []): LengthAwarePaginator
    {
        return Tour::where('partner_id', $partnerId)
            ->when($filters['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status),
                fn ($q) => $q->where('status', '!=', 'archived')
            )
            ->orderByDesc('updated_at')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function getForPartner(int $tourId, int $partnerId): ?Tour
    {
        return Tour::where('id', $tourId)
            ->where('partner_id', $partnerId)
            ->first();
    }

    /**
     * Scoped owner policy: return the partner-owned tour or abort 404 for
     * cross-partner and unknown ids. Used by request authorization (before
     * validation) and controller handlers alike so ownership has one home.
     */
    public function requireOwnedTour(int $tourId, int $partnerId): Tour
    {
        $tour = $tourId > 0 ? $this->getForPartner($tourId, $partnerId) : null;

        if (! $tour) {
            throw new NotFoundHttpException;
        }

        return $tour;
    }

    /**
     * Resolve the category slug from the request payload to a category_id,
     * mirroring the historical controller behavior (required on create,
     * optional on update, unknown slugs rejected with 422).
     */
    public function resolveCategoryId(array $data, bool $required): array
    {
        if (! array_key_exists('category', $data)) {
            if ($required && empty($data['category_id'])) {
                throw new UnprocessableEntityHttpException('The category field is required.');
            }

            return $data;
        }

        $category = Category::where('slug', $data['category'])->first();
        if (! $category) {
            throw new UnprocessableEntityHttpException('Unknown category: ' . $data['category']);
        }

        $data['category_id'] = $category->id;

        return $data;
    }

    private function generatedTourSlug(string $title): string
    {
        // The suffix also separates simultaneous creates with identical titles.
        $prefix = Str::limit(Str::slug($title) ?: 'tour', 200, '');

        return rtrim($prefix, '-') . '-' . Str::lower((string) Str::ulid());
    }

    /**
     * Resolve the authored source locale requested for a new tour.
     *
     * The Request boundary already rejects unsupported values; this guard
     * keeps direct service callers (seeders, tests, internal flows) on the
     * documented English default rather than persisting junk.
     */
    private function resolveSourceLocale(array $data): string
    {
        $locale = $data['source_locale'] ?? null;

        return is_string($locale) && in_array($locale, Tour::CONTENT_LOCALES, true)
            ? $locale
            : Tour::DEFAULT_SOURCE_LOCALE;
    }

    private function creationFingerprint(array $data, string $sourceLocale): string
    {
        $patch = TourContentRules::normalizeSourcePatch($data, $sourceLocale);
        if (array_key_exists('itinerary', $patch)) {
            $patch['itinerary'] = TourContentRules::castItineraryInts(
                TourContentRules::normalizeItinerary($patch['itinerary'])
            );
        }
        $options = TourContentRules::normalizeOptions($data['options'] ?? [], stripIds: true);

        $media = [];
        if (is_array($data['media'] ?? null)) {
            foreach (array_values($data['media']) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $media[] = [
                    'url' => (string) ($item['url'] ?? ''),
                    'is_cover' => ! empty($item['is_cover']),
                    'alt_text' => array_key_exists('alt_text', $item) ? $item['alt_text'] : null,
                ];
            }
        }

        $pricingTiers = [];
        if (is_array($data['pricing_tiers'] ?? null)) {
            foreach (array_values($data['pricing_tiers']) as $tier) {
                if (! is_array($tier)) {
                    continue;
                }
                $pricingTiers[] = [
                    'name' => (string) ($tier['name'] ?? ''),
                    'price' => isset($tier['price']) ? (float) $tier['price'] : 0.0,
                    'currency' => (string) ($tier['currency'] ?? 'EUR'),
                    'min_participants' => isset($tier['min_participants']) ? (int) $tier['min_participants'] : 1,
                    'max_participants' => isset($tier['max_participants']) ? (int) $tier['max_participants'] : null,
                ];
            }
        }

        return hash('sha256', (string) json_encode([
            'category_id' => $data['category_id'] ?? null,
            'slug' => $data['slug'] ?? null,
            'location' => $data['location'] ?? $data['destination'] ?? '',
            'source_locale' => $sourceLocale,
            'title' => $patch['title'] ?? null,
            'description' => $patch['description'] ?? null,
            'highlights' => $patch['highlights'] ?? null,
            'inclusions' => $patch['inclusions'] ?? null,
            'exclusions' => $patch['exclusions'] ?? null,
            'important_information' => $patch['important_information'] ?? null,
            'meeting_point' => $patch['meeting_point'] ?? null,
            'cancellation_policy' => $patch['cancellation_policy'] ?? null,
            'itinerary' => $patch['itinerary'] ?? null,
            'guide_languages' => $this->resolveGuideLanguages($data, []),
            'cover_image_url' => $data['cover_image_url'] ?? null,
            'media' => $media,
            'pricing_tiers' => $pricingTiers,
            'availability_rules' => array_values(is_array($data['availability_rules'] ?? null) ? $data['availability_rules'] : []),
            'availability_exceptions' => array_values(is_array($data['availability_exceptions'] ?? null) ? $data['availability_exceptions'] : []),
            'duration_minutes' => isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            'duration_value' => isset($data['duration_value']) ? (int) $data['duration_value'] : null,
            'duration_unit' => $data['duration_unit'] ?? null,
            'difficulty_level' => $data['difficulty_level'] ?? null,
            'group_size_min' => isset($data['group_size_min']) ? (int) $data['group_size_min'] : null,
            'group_size_max' => isset($data['group_size_max']) ? (int) $data['group_size_max'] : null,
            'price_amount' => isset($data['price_amount']) ? (int) $data['price_amount'] : null,
            'price_from' => isset($data['price_from']) ? (float) $data['price_from'] : null,
            'currency' => $data['currency'] ?? null,
            'options' => $options,
        ]));
    }

    private function normalizeCreationUuid(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = strtolower(trim($value));

        return $trimmed === '' ? null : $trimmed;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        if ($sqlState === '23505' || (string) $exception->getCode() === '23505') {
            return true;
        }

        return $sqlState === '23000' && str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    public function createTour(int $partnerId, array $data): Tour
    {
        $bodyCreationId = $this->normalizeCreationUuid($data['creation_id'] ?? null);
        $headerCreationId = $this->normalizeCreationUuid(request()->header('Client-Creation-Id'));
        if ($bodyCreationId !== null && $headerCreationId !== null && $bodyCreationId !== $headerCreationId) {
            throw ValidationException::withMessages([
                'creation_id' => ['Body creation_id and Client-Creation-Id header must match.'],
            ]);
        }

        $creationId = $bodyCreationId ?? $headerCreationId;
        if ($creationId !== null && ! Str::isUuid($creationId)) {
            throw ValidationException::withMessages([
                'creation_id' => ['The creation ID must be a valid UUID.'],
            ]);
        }

        [$tour, $planned] = DB::transaction(function () use ($partnerId, $data, $creationId): array {
            $sourceLocale = $this->resolveSourceLocale($data);
            $draft = null;
            $fingerprint = null;
            $existedBeforeCreate = false;

            if ($creationId !== null) {
                $fingerprint = $this->creationFingerprint($data, $sourceLocale);

                // First-Save Idempotency (D2): serialize under PostgreSQL row locks + partial unique index.
                $draft = TourDraft::where('partner_id', $partnerId)
                    ->where('creation_id', $creationId)
                    ->lockForUpdate()
                    ->first();

                $existedBeforeCreate = ($draft !== null);

                if ($draft === null) {
                    try {
                        $draft = DB::transaction(fn (): TourDraft => TourDraft::create([
                            'partner_id' => $partnerId,
                            'creation_id' => $creationId,
                            'status' => 'draft',
                            'current_step' => 'details',
                            'source_locale' => $sourceLocale,
                            'creation_payload_hash' => $fingerprint,
                            'payload' => $data,
                            'auto_saved_at' => now(),
                        ]));
                    } catch (QueryException $exception) {
                        if (! $this->isUniqueViolation($exception)) {
                            throw $exception;
                        }

                        // A competing transaction inserted first; wait for its commit under READ COMMITTED.
                        $draft = TourDraft::where('partner_id', $partnerId)
                            ->where('creation_id', $creationId)
                            ->lockForUpdate()
                            ->first();

                        if ($draft === null) {
                            $draft = TourDraft::create([
                                'partner_id' => $partnerId,
                                'creation_id' => $creationId,
                                'status' => 'draft',
                                'current_step' => 'details',
                                'source_locale' => $sourceLocale,
                                'creation_payload_hash' => $fingerprint,
                                'payload' => $data,
                                'auto_saved_at' => now(),
                            ]);
                        } else {
                            $existedBeforeCreate = true;
                        }
                    }
                }

                if ($draft->tour_id !== null) {
                    $existingTour = Tour::where('id', $draft->tour_id)
                        ->where('partner_id', $partnerId)
                        ->first();

                    $storedFingerprint = $draft->creation_payload_hash ?? ($draft->payload['_fingerprint'] ?? null);
                    if (! $existingTour || $storedFingerprint === null || ! hash_equals($storedFingerprint, $fingerprint)) {
                        throw new TourDraftConflictException(
                            'CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD',
                            'Creation ID was already used with a materially different payload.',
                            ['tour_id' => $draft->tour_id]
                        );
                    }

                    return [$existingTour, []];
                }

                // Never resurrect a discarded or non-active unlinked creation_id.
                if ($existedBeforeCreate && $draft->status !== 'draft') {
                    throw new TourDraftConflictException(
                        'CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD',
                        'Creation ID was already used by an inactive or discarded draft.',
                        ['id' => $draft->id]
                    );
                }
            }

            $patch = TourContentRules::normalizeSourcePatch($data, $sourceLocale);
            $tour = Tour::create([
                'partner_id' => $partnerId,
                'category_id' => $data['category_id'] ?? null,
                'slug' => $data['slug'] ?? $this->generatedTourSlug($patch['title'] ?? ''),
                'source_locale' => $sourceLocale,
                'location' => $data['location'] ?? $data['destination'] ?? '',
                'location_slug' => $data['location_slug'] ?? Str::slug($data['location'] ?? $data['destination'] ?? 'location'),
                'duration_minutes' => $data['duration_minutes'] ?? (($data['duration_unit'] ?? '') === 'day' ? ($data['duration_value'] ?? 1) * 1440 : ($data['duration_value'] ?? 1) * 60),
                'duration_label' => $data['duration_label'] ?? (($data['duration_value'] ?? 1) . ' ' . ($data['duration_unit'] ?? 'hour') . (($data['duration_value'] ?? 1) > 1 ? 's' : '')),
                'group_size_min' => $data['group_size_min'] ?? 1,
                'group_size_max' => $data['group_size_max'] ?? 10,
                'price_amount' => $data['price_amount'] ?? (int) (($data['price_from'] ?? 0) * 100),
                // Creation always materializes a draft; supplied `status` is
                // prohibited at the Request boundary and never trusted here.
                'status' => 'draft',
                'cover_image_url' => $data['cover_image_url'] ?? null,
                'difficulty_level' => $data['difficulty_level'] ?? null,
                'guide_languages' => $this->resolveGuideLanguages($data, []),
            ]);

            if (array_key_exists('media', $data)) {
                $this->syncMedia($tour, $data['media']);
            }

            // Canonical source patch: explicit nested keys win, shorthand
            // fills absent keys, omitted keys preserve (nothing to preserve
            // on create). Media above and source here commit atomically.
            if (array_key_exists('itinerary', $patch)) {
                $patch['itinerary'] = TourContentRules::castItineraryInts(
                    TourContentRules::normalizeItinerary($patch['itinerary'])
                );
            }
            if ($patch !== []) {
                $this->syncTranslations($tour, $sourceLocale, $patch);
            }

            // Options sync inside the create transaction and BEFORE the
            // generation plan: option texts are part of the source hash, so a
            // tour created with options must already plan them. On create an
            // empty list simply means "no options configured" and every
            // option is a fresh row (authored ids are rejected at the Request
            // boundary).
            if (! empty($data['options'])) {
                $this->syncOptions($tour, TourContentRules::normalizeOptions($data['options'], stripIds: true));
            }

            $planned = $this->translationService->planSourceDispatches($tour, null);

            if (! empty($data['pricing_tiers'])) {
                $this->syncPricingTiers($tour, $data['pricing_tiers']);
            }

            if (! empty($data['availability_rules'])) {
                $this->syncAvailabilityRules($tour, $data['availability_rules']);
            }

            if (! empty($data['availability_exceptions'])) {
                $this->syncAvailabilityExceptions($tour, $data['availability_exceptions']);
            }

            if ($draft !== null) {
                $tour->refresh();
                $preserveWizardSnapshot = $existedBeforeCreate && $draft->payload !== [];
                $updatedPayload = $preserveWizardSnapshot
                    ? $this->enrichWizardOptionsWithServerIds($draft->payload, $tour)
                    : $this->buildWizardDraftPayloadFromCreate($tour, $data, $patch, $sourceLocale);

                $draft->update([
                    'tour_id' => $tour->id,
                    'status' => 'draft',
                    'current_step' => ($preserveWizardSnapshot && is_string($draft->current_step) && $draft->current_step !== '')
                        ? $draft->current_step
                        : 'details',
                    'source_locale' => $sourceLocale,
                    'concurrency_token' => hash('sha256', (string) Str::uuid() . microtime()),
                    'creation_payload_hash' => $fingerprint,
                    'payload' => $updatedPayload,
                    'auto_saved_at' => now(),
                ]);
            }

            return [$tour, $planned];
        });

        // Generation pushes run only after the source transaction committed:
        // a lost queue push is contained per locale and can never fail the
        // accepted source write.
        $this->translationService->dispatchPlannedTranslations($planned);

        return $tour;
    }

    public function updateTour(Tour $tour, array $data): Tour
    {
        $expectedPartnerId = (int) $tour->partner_id;
        // Tour-first lock boundary (Tour -> authored source -> derivative
        // states in stable locale order); the fresh source is rechecked
        // inside before mutating state. Generation plans persist inside the
        // lock; pushes run after commit.
        [$freshTour, $planned] = $this->translationService->withContentLock($tour->id, function () use ($tour, $data, $expectedPartnerId) {
            $fresh = Tour::findOrFail($tour->id);
            if ((int) $fresh->partner_id !== $expectedPartnerId) {
                throw new NotFoundHttpException('Tour not found.');
            }
            $sourceLocale = $fresh->sourceLocale();
            $existingSource = $fresh->translations()->where('locale', $sourceLocale)->first();
            $previousHash = $existingSource ? $this->translationService->sourceHash($existingSource) : null;

            // EGM-003 published-content fence (design v3 §4): everything the
            // partner supplies for a published tour is compared against the
            // CURRENT live approved values under this same Tour lock. Any
            // changed governed value refuses the whole atomic save with one
            // 422 `content_requires_revision`; no operational sub-write runs.
            // Unchanged round-tripped content may accompany a legitimate
            // operational save, and the operational-only groups below are
            // then applied alone.
            $operationalOnly = $data;

            if ($fresh->status === 'published') {
                $operationalOnly = $this
                    ->revisionService()
                    ->governedDiffForOperationalSave($fresh, $data);
            }

            // Canonical source patch with the same precedence/omission
            // semantics as create; omitted keys stay absent and preserve.
            // `source_locale` is immutable and rejected before this point.
            $patch = TourContentRules::normalizeSourcePatch($operationalOnly, $sourceLocale);
            if (array_key_exists('itinerary', $patch)) {
                $patch['itinerary'] = TourContentRules::castItineraryInts(
                    TourContentRules::normalizeItinerary($patch['itinerary'])
                );
            }

            // Published or approval-bound source content must not be cleared:
            // a clearing update (null/blank title or description) fails
            // atomically instead of partially wiping live source.
            if (in_array($fresh->status, ['published', 'pending_review'], true)) {
                if (array_key_exists('title', $patch) && ! is_string($patch['title'] ?? null)) {
                    throw ValidationException::withMessages([
                        "translations.{$sourceLocale}.title" => 'Published content requires a source title.',
                    ]);
                }
                if (array_key_exists('title', $patch) && trim((string) $patch['title']) === '') {
                    throw ValidationException::withMessages([
                        "translations.{$sourceLocale}.title" => 'Published content requires a source title.',
                    ]);
                }
                if (array_key_exists('description', $patch) && trim((string) ($patch['description'] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        "translations.{$sourceLocale}.description" => 'Published content requires a source description.',
                    ]);
                }
            }

            $updateFields = [];
            if (isset($operationalOnly['category_id'])) {
                $updateFields['category_id'] = $operationalOnly['category_id'];
            }
            if (isset($operationalOnly['slug'])) {
                $updateFields['slug'] = $operationalOnly['slug'];
            }
            if (isset($operationalOnly['location'])) {
                $updateFields['location'] = $operationalOnly['location'];
                $updateFields['location_slug'] = Str::slug($operationalOnly['location']);
            } elseif (isset($operationalOnly['destination'])) {
                // The update contract accepts the historical `destination`
                // alias; it maps onto the stored location, mirroring create.
                $updateFields['location'] = $operationalOnly['destination'];
                $updateFields['location_slug'] = Str::slug($operationalOnly['destination']);
            }
            if (isset($operationalOnly['duration_value']) || isset($operationalOnly['duration_unit'])) {
                $val = $operationalOnly['duration_value'] ?? ($fresh->duration_minutes / 60);
                $unit = $operationalOnly['duration_unit'] ?? 'hour';
                $updateFields['duration_minutes'] = $unit === 'day' ? $val * 1440 : $val * 60;
                $updateFields['duration_label'] = $val . ' ' . $unit . ($val > 1 ? 's' : '');
            }
            if (isset($operationalOnly['group_size_min'])) {
                $updateFields['group_size_min'] = $operationalOnly['group_size_min'];
            }
            if (isset($operationalOnly['group_size_max'])) {
                $updateFields['group_size_max'] = $operationalOnly['group_size_max'];
            }
            if (isset($data['price_from'])) {
                $updateFields['price_amount'] = (int) ($data['price_from'] * 100);
            }
            if (isset($operationalOnly['difficulty_level'])) {
                $updateFields['difficulty_level'] = $operationalOnly['difficulty_level'];
            }
            if (array_key_exists('cover_image_url', $operationalOnly)) {
                $updateFields['cover_image_url'] = $operationalOnly['cover_image_url'];
            }
            if (array_key_exists('guide_languages', $operationalOnly) || array_key_exists('languages', $operationalOnly)) {
                $updateFields['guide_languages'] = $this->resolveGuideLanguages($operationalOnly, null);
            }

            if (! empty($updateFields)) {
                $fresh->update($updateFields);
            }

            if (array_key_exists('media', $operationalOnly)) {
                $this->syncMedia($fresh, $operationalOnly['media']);
            }

            // Only present patch keys are written: omitted values preserve
            // old rows, explicit null clears nullable text, [] clears lists.
            if ($patch !== []) {
                $this->syncTranslations($fresh, $sourceLocale, $patch);
            }

            // Atomic option sync under the same Tour-first lock, BEFORE the
            // generation plan so option-text changes are part of the recomputed
            // source hash. Omitted list preserves; provided list upserts listed
            // ids in authored order and retires the rest (never hard-deletes).
            // On a published tour the governed fence above has already confirmed
            // the option structure and text are unchanged, so the commercial
            // values flow through syncOptions untouched by text.
            if (array_key_exists('options', $operationalOnly) && is_array($operationalOnly['options'])) {
                $this->syncOptions($fresh, TourContentRules::normalizeOptions($operationalOnly['options']));
            }

            $planned = $this->translationService->planSourceDispatches($fresh, $previousHash);

            if (isset($data['pricing_tiers'])) {
                $this->syncPricingTiers($fresh, $data['pricing_tiers']);
            }

            if (isset($data['availability_rules'])) {
                $this->syncAvailabilityRules($fresh, $data['availability_rules']);
            }

            if (isset($data['availability_exceptions'])) {
                $this->syncAvailabilityExceptions($fresh, $data['availability_exceptions']);
            }

            $fresh->touch();

            return [$fresh->fresh(), $planned];
        });

        $this->translationService->dispatchPlannedTranslations($planned);

        return $freshTour;
    }

    /**
     * Upsert the authored source translation row for one locale.
     *
     * Present-key semantics over the canonical source field list: title is
     * written only when supplied non-null; every other present key is written
     * verbatim so explicit null clears nullable text and [] clears lists while
     * omitted keys preserve stored values.
     *
     * @param  array<string, mixed>  $content
     */
    protected function syncTranslations(Tour $tour, string $locale, array $content): void
    {
        // Present-key semantics over the canonical source list: title is
        // written only when supplied non-null; every other present key
        // is written verbatim so explicit null clears nullable text and
        // [] clears lists while omitted keys preserve stored values.
        $updateData = [];
        foreach (TourContentRules::SOURCE_FIELDS as $field) {
            if (! array_key_exists($field, $content)) {
                continue;
            }
            if ($field === 'title' && $content[$field] === null) {
                continue;
            }
            if ($field === 'description' && is_string($content[$field])) {
                $updateData[$field] = app(HtmlSanitizerService::class)->sanitize($content[$field]);
            } else {
                $updateData[$field] = $content[$field];
            }
        }

        if ($updateData !== []) {
            $tour->translations()->updateOrCreate(
                ['locale' => $locale],
                $updateData
            );
        }
    }

    /**
     * Canonical guide-language resolution (Spec 019 US4 T050/T051).
     *
     * Canonical `guide_languages` presence wins over the `languages`
     * compatibility alias even when [] or null; omitted canonical accepts
     * the alias. Null is stored null (the existing reader represents it as
     * empty); otherwise codes normalize to stable lowercase distinct values
     * preserving first-seen order. Never infers codes from derivatives.
     */
    private function resolveGuideLanguages(array $data, mixed $default): ?array
    {
        if (array_key_exists('guide_languages', $data)) {
            return $this->normalizeGuideLanguages($data['guide_languages']);
        }

        if (array_key_exists('languages', $data)) {
            return $this->normalizeGuideLanguages($data['languages']);
        }

        return $this->normalizeGuideLanguages($default);
    }

    /**
     * @param  array<int, string>|null  $languages
     */
    private function normalizeGuideLanguages(mixed $languages): ?array
    {
        if ($languages === null) {
            return null;
        }

        $normalized = [];
        foreach ($languages as $language) {
            $code = strtolower(trim($language));
            if ($code === '' || in_array($code, $normalized, true)) {
                continue;
            }
            $normalized[] = $code;
        }

        return $normalized;
    }

    private function syncMedia(Tour $tour, array $media): void
    {
        // Spec 019 US4 T051: snapshot prior specific alt by URL before
        // deletion so omitted alt retains it for the same URL while explicit
        // null clears it; new URLs default to null. Storage types are
        // cover/gallery (legacy image stays readable); the first flagged
        // real HTTP(S) image — or the first real image when none is flagged —
        // is the cover. Input sort order is preserved; the public reader
        // (allImageUrls) stays authoritative for cover-first/dedup/fallback.
        $isUsable = static fn (mixed $url): bool => is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);

        $priorAlt = [];
        foreach ($tour->media()->get(['url', 'alt_text']) as $row) {
            $url = $row->getAttribute('url');
            if (! is_string($url) || array_key_exists($url, $priorAlt)) {
                continue;
            }
            $priorAlt[$url] = $row->getAttribute('alt_text');
        }

        $cover = null;
        foreach ($media as $image) {
            if (! empty($image['is_cover']) && $isUsable($image['url'] ?? null)) {
                $cover = $image['url'];

                break;
            }
        }
        if ($cover === null) {
            foreach ($media as $image) {
                if ($isUsable($image['url'] ?? null)) {
                    $cover = $image['url'];

                    break;
                }
            }
        }

        $tour->media()->delete();
        $coverAssigned = false;
        foreach ($media as $index => $image) {
            $url = $image['url'];
            if (array_key_exists('alt_text', $image)) {
                $alt = $image['alt_text'];
            } else {
                $alt = $priorAlt[$url] ?? null;
            }

            $isCoverRow = $cover !== null && $url === $cover && ! $coverAssigned;
            if ($isCoverRow) {
                $coverAssigned = true;
            }

            $tour->media()->create([
                'type' => $isCoverRow ? 'cover' : 'gallery',
                'url' => $url,
                'alt_text' => $alt,
                'sort_order' => $index,
            ]);
        }
        $tour->update(['cover_image_url' => $cover]);
    }

    /**
     * Scoped owned-detail projection for the partner show endpoint.
     *
     * Keeps the exact ownership 404 and response envelope: owned relations
     * plus current-hash-aware sanitized translation statuses. Operational
     * translation internals (loaded state models, hashes, error categories,
     * provider/job details) are explicitly excluded from serialization;
     * apparent ready rows with a hash mismatch already project as stale via
     * the shared status boundary.
     */
    public function getOwnedTourDetail(int $tourId, int $partnerId): array
    {
        // The governance GET is a governed read/mutation boundary: the baseline
        // of a published legacy tour is lazily captured under the Tour lock
        // BEFORE the response, so a published tour never reports null
        // capability (design v3 §6 §10). Cross-supplier and unknown ids stay
        // the documented scoped 404.
        return $this->revisionService()->withOwnedTour($tourId, $partnerId, function (Tour $tour): array {

            $tour->load(['translations', 'media', 'pricingTiers', 'availabilityRules', 'availabilityExceptions', 'options']);

            $data = $tour->toArray();
            unset($data['translationStates'], $data['translation_states'], $data['options']);
            // Compatible spoken-language alias: derived from the canonical codes
            // so `languages` and `guide_languages` share one meaning (FR-017).
            // Null canonical storage is represented empty by the reader.
            $codes = $tour->guideLanguages();
            $data['guide_languages'] = $codes;
            $data['languages'] = $codes;
            $data['source_locale'] = $tour->sourceLocale();
            // Active purchasable options with stable ids for scoped follow-up
            // updates: a partner round-trips this list, so ids omitted from a
            // later update retire their options and nothing is ever deleted.
            $data['options'] = $this->projectOwnedOptions($tour);
            // Only generated derivatives carry a status; the authored source is
            // served as-is and is reported through `source_locale`.
            $data['translation_statuses'] = [];
            foreach ($tour->derivativeLocales() as $locale) {
                $data['translation_statuses'][$locale] = $this->translationService->publicStatus($tour, $locale);
            }

            $data['content_governance'] = $this->revisionService()->governancePayload($tour)['content_governance'];

            return $data;
        });
    }

    /**
     * Resolve an owned tour under its governance locks (candidate and request
     * rows reconciled), for the partner revision endpoints. Ownership stays the
     * single scoped lookup; foreign or unknown ids remain 404.
     */
    public function getOwnedTourDetailScope(int $tourId, int $partnerId): Tour
    {
        return $this->revisionService()->withOwnedTour($tourId, $partnerId, fn (Tour $tour): Tour => $tour);
    }

    /**
     * The owned `content_governance` projection (design v3 §6). A published
     * legacy tour lazily captures its baseline first, so a published tour never
     * reports null capability; never-public tours expose a real epoch with no
     * invented approved revision.
     *
     * @return array<string, mixed>
     */
    public function ownedContentGovernance(int $tourId, int $partnerId): array
    {
        return $this->revisionService()->withOwnedTour(
            $tourId, $partnerId, fn (Tour $tour): array => $this->revisionService()->governancePayload($tour),
        );
    }

    public function submitForReview(Tour $tour): Tour
    {
        // Trimmed required source content under the shared Tour-first lock.
        // Derivative readiness is never required; lifecycle/auth/pricing/media
        // guards stay at their existing boundaries and no extra publication
        // policy is added here.
        return $this->translationService->withContentLock($tour->id, function () use ($tour) {
            $fresh = Tour::findOrFail($tour->id);

            // EGM-002: archived and live tours cannot re-enter approval here.
            abort_unless(
                $fresh->status === 'pending_review' || $fresh->canTransitionTo('pending_review'),
                422,
                'Tour cannot be submitted for review from its current state.',
            );

            $sourceLocale = $fresh->sourceLocale();
            $source = $fresh->translations()->where('locale', $sourceLocale)->first();

            $message = $sourceLocale === Tour::DEFAULT_SOURCE_LOCALE
                ? 'Tour must have at least an English title and description.'
                : 'Tour must have at least a title and description in its source language.';

            abort_unless(
                $source
                    && trim((string) $source->title) !== ''
                    && is_string($source->description) && trim($source->description) !== '',
                422,
                $message,
            );

            $fresh->update([
                'status' => 'pending_review',
                'submitted_at' => now(),
            ]);

            TourDraft::where('tour_id', $fresh->id)
                ->where('status', 'draft')
                ->update(['status' => 'submitted']);

            return $fresh->fresh();
        });
    }

    public function archiveTour(Tour $tour): Tour
    {
        // A partner or admin archive leaves the published state: the governed
        // candidate and open published-context requests are reconciled in the
        // same locked tour so a later governance access cannot resurrect them.
        return $this->revisionService()->runReconciliationUnderLock($tour, function () use ($tour): Tour {
            $locked = Tour::query()->lockForUpdate()->findOrFail($tour->id);
            $locked->update([
                'status' => 'archived',
                'archived_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * The revision-governance service accessor. Resolving lazily keeps the
     * existing constructor signature and avoids an early-binding cycle.
     */
    private function revisionService(): TourRevisionService
    {
        return app(TourRevisionService::class);
    }

    private function resumableGeneralDraftQuery(int $partnerId)
    {
        return TourDraft::where('partner_id', $partnerId)
            ->where('status', 'draft')
            ->whereNotNull('creation_id')
            ->where(function ($query): void {
                $query->whereNull('tour_id')
                    ->orWhereHas('tour', fn ($tourQuery) => $tourQuery->where('status', 'draft'));
            })
            ->orderByDesc('auto_saved_at')
            ->orderByDesc('id');
    }

    public function listDraftsForPartner(int $partnerId): array
    {
        return $this->resumableGeneralDraftQuery($partnerId)
            ->get()
            ->all();
    }

    public function getLatestGeneralDraft(int $partnerId): ?TourDraft
    {
        return $this->resumableGeneralDraftQuery($partnerId)
            ->first();
    }

    public function getDraftByCreationId(int $partnerId, string $creationId): ?TourDraft
    {
        $normalized = $this->normalizeCreationUuid($creationId);
        if ($normalized === null || ! Str::isUuid($normalized)) {
            return null;
        }

        return TourDraft::where('partner_id', $partnerId)
            ->where('creation_id', $normalized)
            ->first();
    }

    public function serializeDraftSyncMeta(TourDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'creation_id' => $draft->creation_id,
            'tour_id' => $draft->tour_id,
            'concurrency_token' => $draft->concurrency_token,
            'current_step' => $draft->current_step,
            'source_locale' => $draft->source_locale,
            'auto_saved_at' => $draft->auto_saved_at?->toIso8601String(),
        ];
    }

    private function enrichWizardOptionsWithServerIds(array $payload, Tour $tour): array
    {
        $activeOptions = $tour->activeOptions()->values();
        if ($activeOptions->isEmpty() || ! is_array($payload['options'] ?? null)) {
            return $payload;
        }

        $optionIdx = 0;
        $updatedOptions = [];
        foreach ($payload['options'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $hasContent = trim((string) ($row['title'] ?? '')) !== ''
                || trim((string) ($row['price'] ?? '')) !== ''
                || trim((string) ($row['capacity'] ?? '')) !== ''
                || trim((string) ($row['start_time'] ?? '')) !== ''
                || trim((string) ($row['pickup_instructions'] ?? '')) !== ''
                || (! empty($row['guide_languages']))
                || (! empty($row['weekdays']));

            if ($hasContent && isset($activeOptions[$optionIdx])) {
                $row['server_id'] = (int) $activeOptions[$optionIdx]->id;
                $optionIdx++;
            }
            $updatedOptions[] = $row;
        }

        $payload['options'] = $updatedOptions;

        return $payload;
    }

    private function formatCentsAsMajorString(int $cents): string
    {
        $formatted = number_format($cents / 100, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private function buildWizardDraftPayloadFromCreate(Tour $tour, array $data, array $patch, string $sourceLocale): array
    {
        $durationMinutes = (int) ($tour->duration_minutes ?? 60);
        $durationUnit = $data['duration_unit'] ?? ($durationMinutes >= 1440 && $durationMinutes % 1440 === 0 ? 'day' : 'hour');
        $durationValue = $data['duration_value'] ?? ($durationUnit === 'day' ? (int) max(1, round($durationMinutes / 1440)) : (int) max(1, round($durationMinutes / 60)));

        $mediaRows = [];
        $rawMedia = is_array($data['media'] ?? null) ? array_values($data['media']) : [];
        foreach ($rawMedia as $index => $item) {
            if (! is_array($item) || empty($item['url'])) {
                continue;
            }
            $row = [
                'id' => (string) ($item['id'] ?? "srv-media-{$index}"),
                'url' => (string) $item['url'],
                'is_cover' => ! empty($item['is_cover']),
                'sort_order' => (int) ($item['sort_order'] ?? $index),
            ];
            if (array_key_exists('alt_text', $item)) {
                $row['alt_text'] = $item['alt_text'];
            }
            $mediaRows[] = $row;
        }
        if ($mediaRows === [] && ! empty($tour->cover_image_url)) {
            $mediaRows[] = [
                'id' => 'srv-media-0',
                'url' => (string) $tour->cover_image_url,
                'is_cover' => true,
                'sort_order' => 0,
            ];
        }

        $optionRows = $tour->activeOptions()->values()->map(function (TourOption $option): array {
            $startTime = $option->startTimeString();

            return [
                'id' => "srv-option-{$option->id}",
                'server_id' => (int) $option->id,
                'title' => (string) $option->title,
                'pickup_instructions' => (string) ($option->pickup_instructions ?? ''),
                'guide_languages' => $option->guideLanguages(),
                'price' => $option->price_amount > 0 ? $this->formatCentsAsMajorString((int) $option->price_amount) : '',
                'capacity' => $option->group_size_max !== null ? (string) $option->group_size_max : '',
                'start_time' => $startTime !== null ? substr($startTime, 0, 5) : '',
                'weekdays' => is_array($option->weekdays) ? array_values($option->weekdays) : [],
            ];
        })->all();

        $tierRows = [];
        foreach (PricingTier::where('tour_id', $tour->id)->whereNull('tour_option_id')->orderBy('id')->get() as $idx => $tier) {
            $tierRows[] = [
                'id' => "srv-tier-{$tier->id}-{$idx}",
                'name' => (string) $tier->name,
                'price' => (string) $tier->price,
                'currency' => (string) ($data['currency'] ?? 'EUR'),
                'min_participants' => (int) $tier->min_participants,
                'max_participants' => $tier->max_participants !== null ? (int) $tier->max_participants : null,
            ];
        }

        return [
            'source_locale' => $sourceLocale,
            'title' => (string) ($patch['title'] ?? ''),
            'description' => (string) ($patch['description'] ?? ''),
            'category' => (string) ($data['category'] ?? optional($tour->category)->slug ?? ''),
            'destination' => (string) ($data['destination'] ?? $tour->location ?? ''),
            'duration_value' => (string) $durationValue,
            'duration_unit' => $durationUnit,
            'difficulty_level' => $tour->difficulty_level,
            'itinerary' => $patch['itinerary'] ?? [],
            'highlights' => $patch['highlights'] ?? [],
            'inclusions' => $patch['inclusions'] ?? [],
            'exclusions' => $patch['exclusions'] ?? [],
            'important_information' => $patch['important_information'] ?? [],
            'meeting_point' => (string) ($patch['meeting_point'] ?? ''),
            'languages' => $tour->guideLanguages(),
            'cancellation_policy' => (string) ($patch['cancellation_policy'] ?? ''),
            'media' => $mediaRows,
            'pricing_tiers' => $tierRows,
            'availability_rules' => array_values(is_array($data['availability_rules'] ?? null) ? $data['availability_rules'] : []),
            'availability_exceptions' => array_values(is_array($data['availability_exceptions'] ?? null) ? $data['availability_exceptions'] : []),
            'options' => $optionRows,
            'group_size_min' => (int) ($tour->group_size_min ?? 1),
            'group_size_max' => (int) ($tour->group_size_max ?? 20),
        ];
    }

    private function isActiveResumableDraft(TourDraft $draft): bool
    {
        if ($draft->status !== 'draft') {
            return false;
        }

        if ($draft->tour_id !== null) {
            return Tour::where('id', $draft->tour_id)
                ->where('partner_id', $draft->partner_id)
                ->value('status') === 'draft';
        }

        return true;
    }

    public function getDraftForPartner(int $partnerId, int $draftId): ?TourDraft
    {
        $draft = TourDraft::where('partner_id', $partnerId)
            ->where('id', $draftId)
            ->first();

        if (! $draft || ! $this->isActiveResumableDraft($draft)) {
            return null;
        }

        return $draft;
    }

    private function draftPayloadHasAuthoredContent(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach (['title', 'description', 'meeting_point', 'cancellation_policy'] as $textField) {
            $value = $payload[$textField] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        foreach (['highlights', 'inclusions', 'exclusions', 'important_information', 'itinerary', 'options'] as $listField) {
            $value = $payload[$listField] ?? null;
            if (is_array($value) && $value !== []) {
                return true;
            }
        }

        return false;
    }

    private function buildDraftConflictPayload(TourDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'creation_id' => $draft->creation_id,
            'tour_id' => $draft->tour_id,
            'concurrency_token' => $draft->concurrency_token,
            'updated_at' => $draft->auto_saved_at?->toIso8601String(),
            'current_step' => $draft->current_step,
            'source_locale' => $draft->source_locale,
            'payload' => $draft->payload,
        ];
    }

    public function saveGeneralDraft(int $partnerId, array $data, ?string $concurrencyToken = null): TourDraft
    {
        $normalizedCreationId = $this->normalizeCreationUuid($data['creation_id'] ?? null);

        return DB::transaction(function () use ($partnerId, $data, $concurrencyToken, $normalizedCreationId): TourDraft {
            $draftId = $data['id'] ?? null;
            $draft = null;

            if ($draftId !== null) {
                $draft = TourDraft::where('partner_id', $partnerId)
                    ->where('id', $draftId)
                    ->lockForUpdate()
                    ->first();
                if (! $draft) {
                    throw new NotFoundHttpException('Draft not found.');
                }

                if ($draft->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'status' => ['Only active drafts can be updated.'],
                    ]);
                }

                // Optimistic concurrency check (D1)
                if ($draft->concurrency_token !== null && ($concurrencyToken === null || ! hash_equals($draft->concurrency_token, $concurrencyToken))) {
                    throw new TourDraftConflictException(
                        'STALE_DRAFT_CONFLICT',
                        'This draft was modified from another tab or device.',
                        $this->buildDraftConflictPayload($draft),
                    );
                }
            } elseif ($normalizedCreationId !== null) {
                $draft = TourDraft::where('partner_id', $partnerId)
                    ->where('creation_id', $normalizedCreationId)
                    ->lockForUpdate()
                    ->first();

                if ($draft) {
                    if ($draft->status !== 'draft') {
                        throw ValidationException::withMessages([
                            'status' => ['Only active drafts can be updated.'],
                        ]);
                    }

                    if ($draft->concurrency_token !== null && ($concurrencyToken === null || ! hash_equals($draft->concurrency_token, $concurrencyToken))) {
                        throw new TourDraftConflictException(
                            'STALE_DRAFT_CONFLICT',
                            'This draft was modified from another tab or device.',
                            $this->buildDraftConflictPayload($draft),
                        );
                    }
                }
            }

            if ($draft !== null && $draft->tour_id !== null && array_key_exists('tour_id', $data) && $data['tour_id'] !== null && (int) $data['tour_id'] !== (int) $draft->tour_id) {
                throw ValidationException::withMessages([
                    'tour_id' => ['Draft tour_id cannot be reassigned once linked to a tour.'],
                ]);
            }

            $effectiveTourId = $data['tour_id'] ?? $draft?->tour_id;
            if ($effectiveTourId !== null) {
                $ownedTour = $this->requireOwnedTour((int) $effectiveTourId, $partnerId);
                if ($ownedTour->status === 'published') {
                    throw ValidationException::withMessages([
                        'tour_id' => ['Published tours must be edited through content revisions, not tour drafts.'],
                    ]);
                }
                if ($ownedTour->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'tour_id' => ['Only draft tours can be edited through tour drafts.'],
                    ]);
                }
            }

            if ($draft !== null && $draft->creation_id !== null && $normalizedCreationId !== null && strcasecmp((string) $draft->creation_id, $normalizedCreationId) !== 0) {
                throw ValidationException::withMessages([
                    'creation_id' => ['Draft creation_id cannot be changed once assigned.'],
                ]);
            }

            if (
                $draft !== null
                && $draft->source_locale !== null
                && array_key_exists('source_locale', $data)
                && $data['source_locale'] !== $draft->source_locale
                && ($effectiveTourId !== null || $this->draftPayloadHasAuthoredContent($draft->payload))
            ) {
                throw ValidationException::withMessages([
                    'source_locale' => ['The original writing language cannot be changed once set.'],
                ]);
            }

            $newToken = hash('sha256', (string) Str::uuid() . microtime());

            if (! $draft) {
                $draft = new TourDraft([
                    'partner_id' => $partnerId,
                    'status' => 'draft',
                ]);
            }

            $draft->fill([
                'creation_id' => $draft->creation_id ?? $normalizedCreationId,
                'tour_id' => $effectiveTourId,
                'source_locale' => array_key_exists('source_locale', $data) ? $data['source_locale'] : $draft->source_locale,
                'current_step' => $data['current_step'] ?? ($draft->current_step ?? 'language'),
                'concurrency_token' => $newToken,
                'payload' => $data['payload'],
                'auto_saved_at' => now(),
            ]);

            try {
                DB::transaction(fn () => $draft->save());
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception) || $normalizedCreationId === null) {
                    throw $exception;
                }

                $winner = TourDraft::where('partner_id', $partnerId)
                    ->where('creation_id', $normalizedCreationId)
                    ->lockForUpdate()
                    ->first();

                if (! $winner) {
                    throw $exception;
                }

                throw new TourDraftConflictException(
                    'STALE_DRAFT_CONFLICT',
                    'This draft was modified from another tab or device.',
                    $this->buildDraftConflictPayload($winner),
                );
            }

            return $draft;
        });
    }

    public function resumeDraftForPartner(int $partnerId, int $draftId): array
    {
        $draft = $this->getDraftForPartner($partnerId, $draftId);
        if (! $draft) {
            throw new NotFoundHttpException('Draft not found.');
        }

        return [
            'id' => $draft->id,
            'draft_id' => $draft->id,
            'creation_id' => $draft->creation_id,
            'tour_id' => $draft->tour_id,
            'source_locale' => $draft->source_locale,
            'current_step' => $draft->current_step,
            'concurrency_token' => $draft->concurrency_token,
            'auto_saved_at' => $draft->auto_saved_at?->toIso8601String(),
            'payload' => $draft->payload,
        ];
    }

    public function discardDraftForPartner(int $partnerId, int $draftId): void
    {
        DB::transaction(function () use ($partnerId, $draftId): void {
            $draft = TourDraft::where('partner_id', $partnerId)
                ->where('id', $draftId)
                ->lockForUpdate()
                ->first();

            if (! $draft) {
                throw new NotFoundHttpException('Draft not found.');
            }

            if ($draft->status === 'submitted') {
                throw ValidationException::withMessages([
                    'status' => ['Submitted drafts cannot be discarded.'],
                ]);
            }

            if ($draft->tour_id !== null) {
                $ownedTour = $this->requireOwnedTour((int) $draft->tour_id, $partnerId);
                if ($ownedTour->status === 'published') {
                    throw ValidationException::withMessages([
                        'tour_id' => ['Published tours cannot discard linked drafts directly.'],
                    ]);
                }
                if ($ownedTour->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'tour_id' => ['Only draft tours can discard linked drafts.'],
                    ]);
                }
            }

            // Preserve row & creation_payload_hash so D2 first-save idempotency cannot be bypassed.
            $draft->status = 'discarded';
            $draft->save();
        });
    }

    public function saveDraft(int $partnerId, ?int $tourId, array $payload): TourDraft
    {
        $draft = TourDraft::updateOrCreate(
            [
                'tour_id' => $tourId,
                'partner_id' => $partnerId,
                'status' => 'draft',
            ],
            [
                'payload' => $payload,
                'auto_saved_at' => now(),
            ]
        );

        return $draft;
    }

    public function getLatestDraft(int $partnerId, int $tourId): ?TourDraft
    {
        return TourDraft::where('partner_id', $partnerId)
            ->where('tour_id', $tourId)
            ->orderByDesc('auto_saved_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Atomic purchasable-option sync under the caller's Tour-first lock /
     * create transaction.
     *
     * Contract (stable ids, no hard-delete):
     *  - omitted list → the caller does not invoke this method at all;
     *  - listed ids → those options are upserted and re-activated in authored
     *    order (`sort_order` = list index); a retied id re-listed returns to
     *    sale without losing identity or its generated texts;
     *  - ids absent from a provided list → retired (status + retired_at),
     *    never deleted, so historical bookings keep their stable reference;
     *  - items without id → new active rows appended in authored order.
     *
     * Only title/pickup (text) and the active flag/order participate in the
     * translation revision; price/capacity/start-time/weekdays/guide codes are
     * structured values a translator never consumes, so updating them alone
     * must not invalidate a translation (the source hash excludes them).
     *
     * @param  array<int, array<string, mixed>>  $options  normalized rows
     */
    protected function syncOptions(Tour $tour, array $options): void
    {
        $existing = $tour->options()->get()->keyBy('id');
        $keptIds = [];

        foreach (array_values($options) as $index => $item) {
            $payload = [
                'title' => (string) ($item['title'] ?? ''),
                'pickup_instructions' => $item['pickup_instructions'] ?? null,
                'guide_languages' => is_array($item['guide_languages'] ?? null) ? $item['guide_languages'] : [],
                'price_amount' => (int) ($item['price_amount'] ?? 0),
                'group_size_max' => $item['group_size_max'] ?? null,
                'start_time' => $item['start_time'] ?? null,
                'weekdays' => $item['weekdays'] ?? null,
                'sort_order' => $index,
                'status' => TourOption::STATUS_ACTIVE,
                'retired_at' => null,
            ];

            $id = $item['id'] ?? null;
            $option = $id !== null ? $existing->get($id) : null;

            if ($option === null && $id !== null) {
                // Scoped-ID validation at the Request boundary makes this
                // unreachable through the API; throw rather than silently
                // rewriting a foreign row for direct service callers.
                throw ValidationException::withMessages([
                    "options.{$index}.id" => 'The selected option does not belong to this tour.',
                ]);
            }

            if ($option !== null) {
                $option->update($payload);
            } else {
                $option = TourOption::create(array_merge(['tour_id' => $tour->id], $payload));
            }

            $keptIds[] = (int) $option->id;
        }

        // Everything not re-listed retires atomically inside the same lock.
        $retireQuery = $tour->options()->where('status', TourOption::STATUS_ACTIVE);
        if ($keptIds !== []) {
            $retireQuery->whereNotIn('id', $keptIds);
        }
        $retireQuery->update([
            'status' => TourOption::STATUS_RETIRED,
            'retired_at' => now(),
        ]);
    }

    /**
     * The partner-facing option projection for the owned-detail endpoint:
     * active options only, with stable ids for scoped follow-up updates. An
     * id omitted from a later update retires the option, so retired rows stay
     * out of the authored set the partner round-trips.
     */
    protected function projectOwnedOptions(Tour $tour): array
    {
        return $tour->activeOptions()
            ->map(fn (TourOption $option): array => [
                'id' => $option->id,
                'title' => $option->title,
                'pickup_instructions' => $option->pickup_instructions,
                'guide_languages' => $option->guideLanguages(),
                'price_amount' => (int) $option->price_amount,
                'group_size_max' => $option->group_size_max,
                'start_time' => $option->startTimeString(),
                'weekdays' => $option->weekdays,
            ])
            ->all();
    }

    protected function syncPricingTiers(Tour $tour, array $tiers): void
    {
        PricingTier::where('tour_id', $tour->id)
            ->whereNull('tour_option_id')
            ->delete();
        foreach ($tiers as $tier) {
            PricingTier::create([
                'tour_id' => $tour->id,
                'name' => $tier['name'],
                'price' => $tier['price'],
                'min_participants' => $tier['min_participants'] ?? 1,
                'max_participants' => $tier['max_participants'] ?? null,
            ]);
        }
    }

    protected function syncAvailabilityRules(Tour $tour, array $rules): void
    {
        AvailabilityRule::where('tour_id', $tour->id)->delete();
        foreach ($rules as $rule) {
            AvailabilityRule::create([
                'tour_id' => $tour->id,
                'rule_type' => $rule['rule_type'],
                'days_of_week' => $rule['days_of_week'] ?? null,
                'start_time' => $rule['start_time'] ?? null,
                'start_date' => $rule['start_date'] ?? null,
                'end_date' => $rule['end_date'] ?? null,
                'capacity' => $rule['capacity'],
            ]);
        }
    }

    protected function syncAvailabilityExceptions(Tour $tour, array $exceptions): void
    {
        AvailabilityException::where('tour_id', $tour->id)->delete();
        foreach ($exceptions as $exception) {
            AvailabilityException::create([
                'tour_id' => $tour->id,
                'exception_type' => $exception['exception_type'],
                'date' => $exception['date'],
                'start_time' => $exception['start_time'] ?? null,
                'capacity' => $exception['capacity'] ?? null,
                'price_multiplier' => $exception['price_multiplier'] ?? '1.00',
                'note' => $exception['note'] ?? null,
            ]);
        }
    }
}
