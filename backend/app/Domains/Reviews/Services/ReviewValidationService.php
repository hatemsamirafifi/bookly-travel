<?php

namespace App\Domains\Reviews\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Models\Review;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReviewValidationService
{
    /**
     * @throws HttpException
     */
    public function validate(Booking $booking, User $traveler): void
    {
        if ($booking->status !== Booking::STATUS_COMPLETED) {
            throw new HttpException(403, 'Reviews can only be submitted for completed bookings.');
        }

        if ($booking->traveler_id !== $traveler->id) {
            throw new HttpException(403, 'You can only review your own bookings.');
        }

        if ($booking->tour_date === null || now()->gt($booking->tour_date->addDays(30))) {
            throw new HttpException(403, 'The review window (30 days after tour date) has closed.');
        }

        if (Review::where('booking_id', $booking->id)->exists()) {
            throw new HttpException(403, 'You have already submitted a review for this booking.');
        }

        $hasPayment = Payment::where('booking_id', $booking->id)
            ->where('status', 'succeeded')
            ->exists();

        if (! $hasPayment) {
            throw new HttpException(403, 'A successful payment record is required to submit a review.');
        }
    }
}
