<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Models\PricingTier;
use App\Models\Tour;
use App\Models\TourOption;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service governing participant-count tiered pricing (EGM-005 D5).
 *
 * Enforces:
 * - Option-level tiered pricing where total participant count determines
 *   the applicable unit price for all participants in the booking.
 * - Validation: min_participants >= 1, max_participants >= min or null,
 *   non-overlapping ranges, contiguous coverage across option capacity,
 *   non-negative integer minor units (cents).
 * - Authoritative unit and total price calculations in minor units (cents).
 */
class PricingTierService
{
    /**
     * Validate an array of pricing tiers against an option's capacity.
     *
     * Rules:
     * 1. Must contain at least one tier.
     * 2. First tier must start at min_participants = 1.
     * 3. Ranges must be contiguous with no gaps or overlaps.
     * 4. Only the last tier may have max_participants = null (open-ended).
     * 5. If the last tier has max_participants set, it must cover up to $capacity.
     * 6. Unit prices must be non-negative integers in cents.
     *
     * @param  array<int, array<string, mixed>>  $tiers
     *
     * @throws ValidationException
     */
    public function validateTiers(array $tiers, int $capacity = 10): void
    {
        if (empty($tiers)) {
            throw ValidationException::withMessages([
                'tiers' => ['At least one pricing tier must be defined.'],
            ]);
        }

        // Sort tiers by min_participants ascending
        $sorted = $tiers;
        usort($sorted, function ($a, $b) {
            $minA = (int) ($a['min_participants'] ?? 0);
            $minB = (int) ($b['min_participants'] ?? 0);

            return $minA <=> $minB;
        });

        $count = count($sorted);

        // Rule 2: First tier must start at 1
        $firstMin = (int) ($sorted[0]['min_participants'] ?? 0);
        if ($firstMin !== 1) {
            throw ValidationException::withMessages([
                'tiers' => ['First pricing tier must start with min_participants = 1.'],
            ]);
        }

        for ($i = 0; $i < $count; $i++) {
            $current = $sorted[$i];
            $min = (int) ($current['min_participants'] ?? 0);
            $max = array_key_exists('max_participants', $current) && $current['max_participants'] !== null && $current['max_participants'] !== ''
                ? (int) $current['max_participants']
                : null;

            $priceAmount = isset($current['price_amount'])
                ? (int) $current['price_amount']
                : (isset($current['price']) ? (int) round(((float) $current['price']) * 100) : null);

            if ($priceAmount === null || $priceAmount < 0) {
                throw ValidationException::withMessages([
                    "tiers.{$i}.price_amount" => ['Price must be a non-negative integer in cents.'],
                ]);
            }

            if ($min < 1) {
                throw ValidationException::withMessages([
                    "tiers.{$i}.min_participants" => ['min_participants must be at least 1.'],
                ]);
            }

            if ($max !== null && $max < $min) {
                throw ValidationException::withMessages([
                    "tiers.{$i}.max_participants" => ['max_participants must be greater than or equal to min_participants.'],
                ]);
            }

            // Check contiguous ranges
            if ($i < $count - 1) {
                if ($max === null) {
                    throw ValidationException::withMessages([
                        "tiers.{$i}.max_participants" => ['Only the final pricing tier can have an open-ended max_participants.'],
                    ]);
                }

                $nextMin = (int) ($sorted[$i + 1]['min_participants'] ?? 0);
                if ($nextMin !== $max + 1) {
                    throw ValidationException::withMessages([
                        'tiers' => ["Pricing tiers must be contiguous with no gaps or overlaps. Tier {$i} ends at {$max}, but next tier starts at {$nextMin}."],
                    ]);
                }
            } else {
                // Final tier
                if ($max !== null && $max < $capacity) {
                    throw ValidationException::withMessages([
                        "tiers.{$i}.max_participants" => ["The final pricing tier must cover up to the option capacity of {$capacity}."],
                    ]);
                }
            }
        }
    }

    /**
     * Find the single applicable option-scoped tier for a given option and participant count.
     * Options without option-level tiers fall back to the option's own base price rather
     * than inheriting legacy tour-level tiers (preserving option-specific pricing isolation).
     */
    public function findApplicableTier(TourOption $option, int $participantCount): ?PricingTier
    {
        return PricingTier::query()
            ->where('tour_option_id', $option->id)
            ->where('min_participants', '<=', $participantCount)
            ->where(function ($query) use ($participantCount) {
                $query->whereNull('max_participants')
                    ->orWhere('max_participants', '>=', $participantCount);
            })
            ->orderBy('min_participants', 'desc')
            ->first();
    }

    /**
     * Find the single applicable tour-level tier (tour_option_id IS NULL) for an option-less tour.
     */
    public function findApplicableTourTier(Tour $tour, int $participantCount): ?PricingTier
    {
        return PricingTier::query()
            ->where('tour_id', $tour->id)
            ->whereNull('tour_option_id')
            ->where('min_participants', '<=', $participantCount)
            ->where(function ($query) use ($participantCount) {
                $query->whereNull('max_participants')
                    ->orWhere('max_participants', '>=', $participantCount);
            })
            ->orderBy('min_participants', 'desc')
            ->first();
    }

    /**
     * Calculate authoritative price in cents for an option-less tour.
     *
     * @return array{
     *     mode: string,
     *     tier_id: int|null,
     *     tier_name: string|null,
     *     min_participants: int,
     *     max_participants: int|null,
     *     unit_price_cents: int,
     *     total_price_cents: int,
     *     currency: string
     * }
     */
    public function calculateTourPrice(Tour $tour, int $participantCount): array
    {
        $currency = $tour->currency();
        $tier = $this->findApplicableTourTier($tour, $participantCount);

        if ($tier) {
            $unitPrice = $tier->unitPriceCents();

            return [
                'mode' => 'tiered_per_person',
                'tier_id' => $tier->id,
                'tier_name' => $tier->name,
                'min_participants' => $tier->min_participants,
                'max_participants' => $tier->max_participants,
                'unit_price_cents' => $unitPrice,
                'total_price_cents' => $unitPrice * $participantCount,
                'currency' => $tier->currency ?? $currency,
            ];
        }

        $unitPrice = $tour->lowestPriceAmount();

        return [
            'mode' => 'base_price',
            'tier_id' => null,
            'tier_name' => null,
            'min_participants' => 1,
            'max_participants' => null,
            'unit_price_cents' => $unitPrice,
            'total_price_cents' => $unitPrice * $participantCount,
            'currency' => $currency,
        ];
    }

    /**
     * Calculate authoritative price in cents for a given option and participant count.
     *
     * @return array{
     *     mode: string,
     *     tier_id: int|null,
     *     tier_name: string|null,
     *     min_participants: int,
     *     max_participants: int|null,
     *     unit_price_cents: int,
     *     total_price_cents: int,
     *     currency: string
     * }
     */
    public function calculatePrice(TourOption $option, int $participantCount, ?Tour $tour = null): array
    {
        /** @var Tour|null $tour */
        $tour = $tour ?? $option->tour;
        $currency = $tour ? $tour->currency() : 'EUR';

        $tier = $this->findApplicableTier($option, $participantCount);

        if ($tier) {
            $unitPrice = $tier->unitPriceCents();
            $totalPrice = $unitPrice * $participantCount;

            return [
                'mode' => 'tiered_per_person',
                'tier_id' => $tier->id,
                'tier_name' => $tier->name,
                'min_participants' => $tier->min_participants,
                'max_participants' => $tier->max_participants,
                'unit_price_cents' => $unitPrice,
                'total_price_cents' => $totalPrice,
                'currency' => $tier->currency ?? $currency,
            ];
        }

        // Base price fallback from option or tour
        $unitPrice = $option->price_amount ?? ($tour ? $tour->lowestPriceAmount() : 0);
        $totalPrice = $unitPrice * $participantCount;

        return [
            'mode' => 'base_price',
            'tier_id' => null,
            'tier_name' => null,
            'min_participants' => 1,
            'max_participants' => null,
            'unit_price_cents' => $unitPrice,
            'total_price_cents' => $totalPrice,
            'currency' => $currency,
        ];
    }

    /**
     * Atomically sync pricing tiers for a tour option.
     *
     * @param  array<int, array<string, mixed>>  $tiersData
     * @return Collection<int, PricingTier>
     */
    public function syncOptionTiers(TourOption $option, array $tiersData): Collection
    {
        return DB::transaction(function () use ($option, $tiersData) {
            /** @var Tour $tour */
            $tour = Tour::whereKey($option->tour_id)->lockForUpdate()->firstOrFail();
            /** @var TourOption $lockedOption */
            $lockedOption = TourOption::where('tour_id', $tour->id)
                ->whereKey($option->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOption->status !== TourOption::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'tour_option_id' => ['Cannot configure pricing tiers for a retired tour option.'],
                ]);
            }

            $capacity = $lockedOption->effectiveCapacity($tour);
            $this->validateTiers($tiersData, $capacity);

            // Delete existing option-level tiers
            PricingTier::where('tour_option_id', $lockedOption->id)->delete();

            $sorted = $tiersData;
            usort(
                $sorted,
                fn (array $a, array $b): int => (int) ($a['min_participants'] ?? 0) <=> (int) ($b['min_participants'] ?? 0)
            );

            $created = new Collection;
            $sortOrder = 0;

            foreach ($sorted as $data) {
                $sortOrder++;
                $priceAmount = isset($data['price_amount'])
                    ? (int) $data['price_amount']
                    : (int) round(((float) ($data['price'] ?? 0)) * 100);

                $tier = PricingTier::create([
                    'tour_id' => $option->tour_id,
                    'tour_option_id' => $option->id,
                    'name' => $data['name'] ?? "Tier {$sortOrder}",
                    'min_participants' => (int) $data['min_participants'],
                    'max_participants' => array_key_exists('max_participants', $data) && $data['max_participants'] !== null && $data['max_participants'] !== ''
                        ? (int) $data['max_participants']
                        : null,
                    'price_amount' => $priceAmount,
                    'price' => number_format($priceAmount / 100, 2, '.', ''),
                    'currency' => $data['currency'] ?? $tour->currency(),
                    'sort_order' => $sortOrder,
                ]);

                $created->push($tier);
            }

            return $created;
        });
    }
}
