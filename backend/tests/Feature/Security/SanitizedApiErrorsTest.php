<?php

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;

it('never exposes exception details in an API 500 response', function () {
    config(['app.debug' => true]);

    Route::get('/api/testing/internal-error', function (): never {
        throw new RuntimeException('database password secret-value at /var/www/private.php:42');
    })->middleware('api');

    $response = getJson('/api/testing/internal-error');

    $response->assertInternalServerError()
        ->assertExactJson([
            'message' => 'An unexpected error occurred. Please try again later.',
        ]);

    expect($response->getContent())
        ->not->toContain('secret-value')
        ->not->toContain('/var/www')
        ->not->toContain('RuntimeException')
        ->not->toContain('trace');
});

it('disables debug mode outside the local environment', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('app.debug'))->toBeFalse();
});
