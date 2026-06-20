<?php

namespace App\Domains\Traveler\Actions;

use App\Domains\Traveler\DTOs\ProfileResponseDTO;
use App\Models\User;

class GetProfileAction
{
    public function execute(User $user): array
    {
        return [
            'data' => ProfileResponseDTO::fromUser($user),
        ];
    }
}
