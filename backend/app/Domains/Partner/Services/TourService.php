<?php

namespace App\Domains\Partner\Services;

use App\Models\Tour;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Models\TourMedia;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\AvailabilityException;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class TourService
{
    public function listForPartner(int $partnerId, array $filters = []): LengthAwarePaginator
    {
        return Tour::where('partner_id', $partnerId)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
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
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'destination' => $data['destination'] ?? null,
                'duration_value' => $data['duration_value'] ?? null,
                'duration_unit' => $data['duration_unit'] ?? null,
                'difficulty_level' => $data['difficulty_level'] ?? null,
                'itinerary' => $data['itinerary'] ?? null,
                'inclusions' => $data['inclusions'] ?? null,
                'meeting_point' => $data['meeting_point'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'price_from' => $data['price_from'] ?? null,
                'currency' => $data['currency'] ?? 'EUR',
            ]);

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
            $tour->update([
                'title' => $data['title'] ?? $tour->title,
                'description' => $data['description'] ?? $tour->description,
                'category' => $data['category'] ?? $tour->category,
                'destination' => $data['destination'] ?? $tour->destination,
                'duration_value' => $data['duration_value'] ?? $tour->duration_value,
                'duration_unit' => $data['duration_unit'] ?? $tour->duration_unit,
                'difficulty_level' => $data['difficulty_level'] ?? $tour->difficulty_level,
                'itinerary' => $data['itinerary'] ?? $tour->itinerary,
                'inclusions' => $data['inclusions'] ?? $tour->inclusions,
                'meeting_point' => $data['meeting_point'] ?? $tour->meeting_point,
                'price_from' => $data['price_from'] ?? $tour->price_from,
                'currency' => $data['currency'] ?? $tour->currency,
            ]);

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
            ->orderByDesc('updated_at')
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