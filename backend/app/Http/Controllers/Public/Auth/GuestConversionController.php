<?php

namespace App\Http\Controllers\Public\Auth;

use App\Domains\Auth\Actions\ConvertGuestToAccountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConvertGuestRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class GuestConversionController extends Controller
{
    /**
     * Handle guest-to-account conversion.
     */
    public function __invoke(ConvertGuestRequest $request, ConvertGuestToAccountAction $action): JsonResponse
    {
        $data = $request->validated();
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = $request->userAgent();

        $result = $action->execute($data);

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'linked_bookings_count' => $result['linked_bookings_count'],
            ],
        ], 201);
    }
}
