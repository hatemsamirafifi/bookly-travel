<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Requests\TourContentRules;
use App\Models\Category;
use App\Models\Tour;
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

    public function createTour(int $partnerId, array $data): Tour
    {
        [$tour, $planned] = DB::transaction(function () use ($partnerId, $data) {
            $patch = TourContentRules::normalizeEnglishPatch($data);
            $tour = Tour::create([
                'partner_id' => $partnerId,
                'category_id' => $data['category_id'] ?? null,
                'slug' => $data['slug'] ?? $this->generatedTourSlug($patch['title'] ?? ''),
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
                'guide_languages' => $this->normalizeGuideLanguages($data['guide_languages'] ?? $data['languages'] ?? []),
            ]);

            if (array_key_exists('media', $data)) {
                $this->syncMedia($tour, $data['media']);
            }

            // Canonical English patch: explicit nested keys win, shorthand
            // fills absent keys, omitted keys preserve (nothing to preserve
            // on create). Media above and source here commit atomically.
            if (array_key_exists('itinerary', $patch)) {
                $patch['itinerary'] = TourContentRules::castItineraryInts(
                    TourContentRules::normalizeItinerary($patch['itinerary'])
                );
            }
            if ($patch !== []) {
                $this->syncTranslations($tour, ['en' => $patch]);
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
        // Tour-first lock boundary (Tour -> EN -> states in es/it order);
        // the fresh source is rechecked inside before mutating state.
        // Generation plans persist inside the lock; pushes run after commit.
        [$freshTour, $planned] = $this->translationService->withContentLock($tour->id, function () use ($tour, $data) {
            $fresh = Tour::findOrFail($tour->id);
            $existingEnglish = $fresh->translations()->where('locale', 'en')->first();
            $previousHash = $existingEnglish ? $this->translationService->sourceHash($existingEnglish) : null;

            // Canonical English patch with the same precedence/omission
            // semantics as create; omitted keys stay absent and preserve.
            $patch = TourContentRules::normalizeEnglishPatch($data);
            if (array_key_exists('itinerary', $patch)) {
                $patch['itinerary'] = TourContentRules::castItineraryInts(
                    TourContentRules::normalizeItinerary($patch['itinerary'])
                );
            }

            // Published or approval-bound English must not be cleared: a
            // clearing update (null/blank title or description) fails
            // atomically instead of partially wiping live source.
            if (in_array($fresh->status, ['published', 'pending_review'], true)) {
                if (array_key_exists('title', $patch) && ! is_string($patch['title'] ?? null)) {
                    throw ValidationException::withMessages([
                        'translations.en.title' => 'Published content requires an English title.',
                    ]);
                }
                if (array_key_exists('title', $patch) && trim((string) $patch['title']) === '') {
                    throw ValidationException::withMessages([
                        'translations.en.title' => 'Published content requires an English title.',
                    ]);
                }
                if (array_key_exists('description', $patch) && trim((string) ($patch['description'] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        'translations.en.description' => 'Published content requires an English description.',
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
                $updateFields['guide_languages'] = $this->normalizeGuideLanguages($data['guide_languages'] ?? $data['languages'] ?? []);
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
                $this->syncTranslations($fresh, ['en' => $patch]);
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

    protected function syncTranslations(Tour $tour, array $translations): void
    {
        foreach ($translations as $locale => $content) {
            if ($locale !== 'en' || ! is_array($content)) {
                continue;
            }
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
                $updateData[$field] = $content[$field];
            }

            if ($updateData !== []) {
                $tour->translations()->updateOrCreate(
                    ['locale' => $locale],
                    $updateData
                );
            }
        }
    }

    private function normalizeGuideLanguages(array $languages): array
    {
        return array_values(array_unique(array_map(
            static fn (string $language): string => strtolower($language),
            $languages
        )));
    }

    private function syncMedia(Tour $tour, array $media): void
    {
        // Media order is the caller's array order. The selected cover is also
        // kept in the legacy cover column for existing search/partner clients.
        $cover = collect($media)->firstWhere('is_cover', true)['url'] ?? ($media[0]['url'] ?? null);
        $tour->media()->delete();
        foreach ($media as $index => $image) {
            $tour->media()->create([
                'type' => 'image',
                'url' => $image['url'],
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
        $tour->load(['translations', 'media', 'pricingTiers', 'availabilityRules', 'availabilityExceptions']);

        $data = $tour->toArray();
        unset($data['translationStates'], $data['translation_states']);
        $data['translation_statuses'] = [
            'es' => $this->translationService->publicStatus($tour, 'es'),
            'it' => $this->translationService->publicStatus($tour, 'it'),
        ];

        return $data;
    }

    public function submitForReview(Tour $tour): Tour
    {
        // Trimmed required English under the shared Tour-first lock. ES/IT
        // readiness is never required; lifecycle/auth/pricing/media guards
        // stay at their existing boundaries and no extra publication policy
        // is added here.
        return $this->translationService->withContentLock($tour->id, function () use ($tour) {
            $fresh = Tour::findOrFail($tour->id);
            $english = $fresh->translations()->where('locale', 'en')->first();

            abort_unless(
                $english
                    && trim($english->title) !== ''
                    && is_string($english->description) && trim($english->description) !== '',
                422,
                'Tour must have at least an English title and description.',
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
