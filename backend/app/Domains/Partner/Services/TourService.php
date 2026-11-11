<?php

namespace App\Domains\Partner\Services;

use App\Domains\CMS\Services\HtmlSanitizerService;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Requests\TourContentRules;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourOption;
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

    public function createTour(int $partnerId, array $data): Tour
    {
        [$tour, $planned] = DB::transaction(function () use ($partnerId, $data) {
            $sourceLocale = $this->resolveSourceLocale($data);
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
        // Tour-first lock boundary (Tour -> authored source -> derivative
        // states in stable locale order); the fresh source is rechecked
        // inside before mutating state. Generation plans persist inside the
        // lock; pushes run after commit.
        [$freshTour, $planned] = $this->translationService->withContentLock($tour->id, function () use ($tour, $data) {
            $fresh = Tour::findOrFail($tour->id);
            $sourceLocale = $fresh->sourceLocale();
            $existingSource = $fresh->translations()->where('locale', $sourceLocale)->first();
            $previousHash = $existingSource ? $this->translationService->sourceHash($existingSource) : null;

            // Canonical source patch with the same precedence/omission
            // semantics as create; omitted keys stay absent and preserve.
            // `source_locale` is immutable and rejected before this point.
            $patch = TourContentRules::normalizeSourcePatch($data, $sourceLocale);
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
            if (isset($data['category_id'])) {
                $updateFields['category_id'] = $data['category_id'];
            }
            if (isset($data['slug'])) {
                $updateFields['slug'] = $data['slug'];
            }
            if (isset($data['location'])) {
                $updateFields['location'] = $data['location'];
                $updateFields['location_slug'] = Str::slug($data['location']);
            } elseif (isset($data['destination'])) {
                // The update contract accepts the historical `destination`
                // alias; it maps onto the stored location, mirroring create.
                $updateFields['location'] = $data['destination'];
                $updateFields['location_slug'] = Str::slug($data['destination']);
            }
            if (isset($data['duration_value']) || isset($data['duration_unit'])) {
                $val = $data['duration_value'] ?? ($fresh->duration_minutes / 60);
                $unit = $data['duration_unit'] ?? 'hour';
                $updateFields['duration_minutes'] = $unit === 'day' ? $val * 1440 : $val * 60;
                $updateFields['duration_label'] = $val . ' ' . $unit . ($val > 1 ? 's' : '');
            }
            if (isset($data['group_size_min'])) {
                $updateFields['group_size_min'] = $data['group_size_min'];
            }
            if (isset($data['group_size_max'])) {
                $updateFields['group_size_max'] = $data['group_size_max'];
            }
            if (isset($data['price_from'])) {
                $updateFields['price_amount'] = (int) ($data['price_from'] * 100);
            }
            if (isset($data['difficulty_level'])) {
                $updateFields['difficulty_level'] = $data['difficulty_level'];
            }
            if (array_key_exists('cover_image_url', $data)) {
                $updateFields['cover_image_url'] = $data['cover_image_url'];
            }
            if (array_key_exists('guide_languages', $data) || array_key_exists('languages', $data)) {
                $updateFields['guide_languages'] = $this->resolveGuideLanguages($data, null);
            }

            if (! empty($updateFields)) {
                $fresh->update($updateFields);
            }

            if (array_key_exists('media', $data)) {
                $this->syncMedia($fresh, $data['media']);
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
            if (array_key_exists('options', $data) && is_array($data['options'])) {
                $this->syncOptions($fresh, TourContentRules::normalizeOptions($data['options']));
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
        $tour = $this->requireOwnedTour($tourId, $partnerId);
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

        return $data;
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

            return $fresh->fresh();
        });
    }

    public function archiveTour(Tour $tour): Tour
    {
        $tour->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        return $tour;
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
        PricingTier::where('tour_id', $tour->id)->delete();
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
