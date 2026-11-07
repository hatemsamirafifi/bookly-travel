<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\HugeRteMediaController;

// Health check for API readiness
Route::get('/', function () {
    return response()->json(['status' => 'ok', 'service' => 'Bookly API']);
});

Route::middleware('auth')->get('/admin/hugerte/media', HugeRteMediaController::class)
    ->name('admin.hugerte.media');
