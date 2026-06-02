<?php

namespace App\Domains\Traveler\DTOs;

use App\Models\User;

class ProfileResponseDTO
{
    public static function fromUser(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
            'phone' => $user->phone,
            'preferred_language' => $user->locale ?? 'en',
            'preferred_currency' => $user->preferred_currency ?? 'EUR',
            'marketing_emails' => $user->marketing_emails ?? false,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
