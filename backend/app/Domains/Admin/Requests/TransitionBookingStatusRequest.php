<?php

namespace App\Domains\Admin\Requests;

use App\Domains\Booking\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the target status for an admin booking transition (Spec 013).
 */
class TransitionBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_status' => ['required', 'string', 'in:' . implode(',', [
                Booking::STATUS_COMPLETED,
                Booking::STATUS_NO_SHOW,
                Booking::STATUS_EXPIRED,
                Booking::STATUS_CANCELLATION_REQUESTED,
                Booking::STATUS_CANCELLED,
            ])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
