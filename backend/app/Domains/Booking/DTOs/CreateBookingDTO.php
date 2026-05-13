<?php

namespace App\Domains\Booking\DTOs;

class CreateBookingDTO
{
    public function __construct(
        public readonly string $tourSlug,
        public readonly string $tourDate,
        public readonly int $participantCount,
        public readonly string $locale,
        public readonly string $idempotencyKey,
        public readonly int $travelerId,
        /** Price per person in cents as displayed on the page at load time (FR-027). */
        public readonly ?int $pageLoadPrice = null,
    ) {}
}
