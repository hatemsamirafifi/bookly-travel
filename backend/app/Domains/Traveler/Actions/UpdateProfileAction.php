<?php

namespace App\Domains\Traveler\Actions;

use App\Domains\Traveler\DTOs\ProfileResponseDTO;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UpdateProfileAction
{
    public function execute(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'preferred_language' => 'required|string|in:en,es,it',
            'preferred_currency' => 'required|string|size:3',
            'marketing_emails' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new UnprocessableEntityHttpException(json_encode($validator->errors()));
        }

        $validated = $validator->validated();

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'locale' => $validated['preferred_language'],
            'preferred_currency' => $validated['preferred_currency'],
            'marketing_emails' => $validated['marketing_emails'] ?? false,
        ]);

        $user->refresh();

        return [
            'data' => ProfileResponseDTO::fromUser($user),
        ];
    }
}
