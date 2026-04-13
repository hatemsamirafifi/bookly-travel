<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| Routes accessible without authentication (auth endpoints, public tours).
| Rate limited: 10 req/min for auth endpoints (per FR-014).
|
| Prefix: /api/public
*/

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    // Auth routes will be registered in Phase 3+ (per user story)
});
