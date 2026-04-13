<?php

use Illuminate\Support\Facades\Route;

// Health check for API readiness
Route::get('/', function () {
    return response()->json(['status' => 'ok', 'service' => 'Bookly API']);
});
