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
    ) {}
}
