<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Models\Tour;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TourService
{
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

    public function createTour(int $partnerId, array $data): Tour
    {
        return DB::transaction(function () use ($partnerId, $data) {
            $tour = Tour::create([
                'partner_id' => $partnerId,
                'category_id' => $data['category_id'] ?? null,
                'slug' => $data['slug'] ?? Str::slug($data['title'] ?? ($data['translations']['en']['title'] ?? 'tour-' . uniqid())),
                'location' => $data['location'] ?? $data['destination'] ?? '',
                'location_slug' => $data['location_slug'] ?? Str::slug($data['location'] ?? $data['destination'] ?? 'location'),
                'duration_minutes' => $data['duration_minutes'] ?? (($data['duration_unit'] ?? '') === 'day' ? ($data['duration_value'] ?? 1) * 1440 : ($data['duration_value'] ?? 1) * 60),
                'duration_label' => $data['duration_label'] ?? (($data['duration_value'] ?? 1) . ' ' . ($data['duration_unit'] ?? 'hour') . (($data['duration_value'] ?? 1) > 1 ? 's' : '')),
                'group_size_min' => $data['group_size_min'] ?? 1,
                'group_size_max' => $data['group_size_max'] ?? 10,
                'price_amount' => $data['price_amount'] ?? (int) (($data['price_from'] ?? 0) * 100),
                'status' => $data['status'] ?? 'draft',
                'cover_image_url' => $data['cover_image_url'] ?? null,
            ]);

            if (! empty($data['translations'])) {
                $this->syncTranslations($tour, $data['translations']);
            } elseif (! empty($data['title'])) {
                // Fallback support for single-title style create payloads
                $this->syncTranslations($tour, [
                    'en' => [
                        'title' => $data['title'],
                        'description' => $data['description'] ?? null,
                        'inclusions' => $data['inclusions'] ?? null,
                        'meeting_point' => $data['meeting_point'] ?? null,
                    ],
                ]);
            }

            if (! empty($data['pricing_tiers'])) {
                $this->syncPricingTiers($tour, $data['pricing_tiers']);
            }

            if (! empty($data['availability_rules'])) {
                $this->syncAvailabilityRules($tour, $data['availability_rules']);
            }

            if (! empty($data['availability_exceptions'])) {
                $this->syncAvailabilityExceptions($tour, $data['availability_exceptions']);
            }

            return $tour;
        });
    }

    public function updateTour(Tour $tour, array $data): Tour
    {
        return DB::transaction(function () use ($tour, $data) {
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
            }
            if (isset($data['duration_value']) || isset($data['duration_unit'])) {
                $val = $data['duration_value'] ?? ($tour->duration_minutes / 60);
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
            if (isset($data['cover_image_url'])) {
                $updateFields['cover_image_url'] = $data['cover_image_url'];
            }

            if (! empty($updateFields)) {
                $tour->update($updateFields);
            }

            if (isset($data['translations'])) {
                $this->syncTranslations($tour, $data['translations']);
            } elseif (isset($data['title'])) {
                $this->syncTranslations($tour, [
                    'en' => [
                        'title' => $data['title'],
                        'description' => $data['description'] ?? null,
                        'inclusions' => $data['inclusions'] ?? null,
                        'meeting_point' => $data['meeting_point'] ?? null,
                    ],
                ]);
            }

            if (isset($data['pricing_tiers'])) {
                $this->syncPricingTiers($tour, $data['pricing_tiers']);
            }

            if (isset($data['availability_rules'])) {
                $this->syncAvailabilityRules($tour, $data['availability_rules']);
            }

            if (isset($data['availability_exceptions'])) {
                $this->syncAvailabilityExceptions($tour, $data['availability_exceptions']);
            }

            return $tour->fresh();
        });
    }

    protected function syncTranslations(Tour $tour, array $translations): void
    {
        foreach ($translations as $locale => $content) {
            $tour->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $content['title'] ?? '',
                    'description' => $content['description'] ?? null,
                    'highlights' => $content['highlights'] ?? null,
                    'inclusions' => $content['inclusions'] ?? null,
                    'exclusions' => $content['exclusions'] ?? null,
                    'meeting_point' => $content['meeting_point'] ?? null,
                    'cancellation_policy' => $content['cancellation_policy'] ?? null,
                ]
            );
        }
    }

    public function submitForReview(Tour $tour): Tour
    {
        $tour->update([
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        return $tour;
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
