<?php

namespace App\Http\Controllers\Public\Auth;

use App\Domains\Auth\Actions\RegisterTravelerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * Handle the incoming registration request.
     */
    public function __invoke(RegisterRequest $request, RegisterTravelerAction $action): JsonResponse
    {
        $data = $request->validated();
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = $request->userAgent();

        $result = $action->execute($data);

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ], 201);
    }
}
