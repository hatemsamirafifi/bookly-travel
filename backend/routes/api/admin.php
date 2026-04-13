<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
| Routes for authenticated admin users.
| Prefix: /api/admin
*/

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Admin routes will be added in spec 011 (Admin Dashboard)
});
