<?php

namespace App\Domains\Traveler\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ChangePasswordAction
{
    public function execute(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            throw new UnprocessableEntityHttpException(json_encode($validator->errors()));
        }

        $validated = $validator->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw new AccessDeniedHttpException('Current password is incorrect.');
        }

        $user->update([
            'password' => $validated['new_password'],
        ]);

        return [
            'message' => 'Password updated successfully.',
        ];
    }
}
