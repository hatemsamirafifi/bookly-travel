<?php

namespace App\Domains\Traveler\Controllers\Public;

use App\Domains\Traveler\Actions\ChangePasswordAction;
use App\Domains\Traveler\Actions\GetProfileAction;
use App\Domains\Traveler\Actions\UpdateProfileAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ProfileController
{
    public function show(Request $request, GetProfileAction $action): JsonResponse
    {
        $result = $action->execute($request->user());

        return response()->json($result);
    }

    public function update(Request $request, UpdateProfileAction $action): JsonResponse
    {
        try {
            $result = $action->execute(
                $request->user(),
                $request->all()
            );

            return response()->json($result);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function changePassword(Request $request, ChangePasswordAction $action): JsonResponse
    {
        try {
            $result = $action->execute(
                $request->user(),
                $request->all()
            );

            return response()->json($result);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
