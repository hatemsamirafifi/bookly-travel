<?php

namespace App\Http\Controllers\Public\Auth;

use App\Domains\Auth\Actions\LogoutTravelerAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * Handle the incoming logout request.
     *
     * Revokes only the current session token. Other sessions remain active.
     */
    public function __invoke(Request $request, LogoutTravelerAction $action)
    {
        $user = $request->user();

        if ($user) {
            $action->execute($user);
        }

        return response()->noContent();
    }
}
