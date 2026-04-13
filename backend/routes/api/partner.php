<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Partner API Routes
|--------------------------------------------------------------------------
| Routes for authenticated partner users.
| Prefix: /api/partner
*/

Route::middleware(['auth:sanctum', 'role:partner'])->group(function () {
    // Partner routes will be added in spec 002 (Partner Onboarding)
});
