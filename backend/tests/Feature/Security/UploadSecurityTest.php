<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Upload Security Suite — /api/partner/uploads/signed-url
|--------------------------------------------------------------------------
| Verifies the signed-URL upload endpoint against its security contract:
| role isolation (traveler → 404, unauthenticated → 401), onboarding
| gating (pending partner → 403 ONBOARDING_STATUS_BLOCKED), MIME/size
| allow-listing, server-side UUID path enforcement, and 15-minute TTL.
*/

it('rejects unauthenticated request with 401', function () {
    postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
    ])->assertStatus(401);
});

it('denies traveler user access to partner upload endpoint', function () {
    $traveler = User::factory()->traveler()->create();
    $travelerToken = $traveler->createToken('test')->plainTextToken;

    // PartnerRoleMiddleware deliberately answers 404 (not 403) for
    // non-partner roles so travelers cannot enumerate partner routes.
    postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
    ], [
        'Authorization' => 'Bearer ' . $travelerToken,
    ])->assertStatus(404);
});

it('denies pending or unapproved partner access to upload endpoint', function () {
    $partner = makePartner('pending');
    $token = $partner->user->createToken('test', ['partner'])->plainTextToken;

    postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(403)
        ->assertJson(['error_code' => 'ONBOARDING_STATUS_BLOCKED']);
});

it('generates signed url for valid jpeg upload', function () {
    $partner = makePartner('approved');
    $token = $partner->user->createToken('test', ['partner'])->plainTextToken;

    $response = postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['signed_url', 'public_url', 'expires_at']);

    $publicUrl = $response->json('public_url');
    $signedUrl = $response->json('signed_url');

    // Filename is generated server-side: UUID + extension matched to MIME.
    expect($publicUrl)->toMatch('#^https://cdn\.bookly\.test/uploads/[a-f0-9\-]{36}\.jpg$#')
        ->and($signedUrl)->toContain('https://r2.bookly.test/uploads/')
        ->and($signedUrl)->toContain('.jpg?sig=');
});

it('generates signed url for valid png upload', function () {
    $partner = makePartner('approved');
    $token = $partner->user->createToken('test', ['partner'])->plainTextToken;

    $response = postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/png',
        'file_size' => 204800,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['signed_url', 'public_url', 'expires_at']);

    expect($response->json('public_url'))->toMatch('#^https://cdn\.bookly\.test/uploads/[a-f0-9\-]{36}\.png$#');
});

it('rejects disallowed mime types', function (string $invalidMime) {
    $partner = makePartner('approved');
    $token = $partner->user->createToken('test', ['partner'])->plainTextToken;

    postJson('/api/partner/uploads/signed-url', [
        'file_type' => $invalidMime,
        'file_size' => 1024,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['file_type']);
})->with([
    'application/pdf',
    'text/html',
    'image/gif',
    'image/webp',
    'application/x-php',
    'application/x-executable',
    'image/jpeg; php',
]);

it('rejects oversized file exceeding 5MB limit', function () {
    $partner = makePartner('approved');
    $token = $partner->user->createToken('test', ['partner'])->plainTextToken;

    postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 5242881,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['file_size']);
});

it('neutralizes path traversal attempts by enforcing server-side UUID paths', function () {
    $partner = makePartner('approved');
    $token = $partner->user->createToken('test', ['partner'])->plainTextToken;

    // No such inputs exist on the contract, so traversal payloads must be
    // ignored entirely — path and filename stay server-generated.
    $response = postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
        'path' => '../../../etc/passwd',
        'filename' => '../../../etc/passwd.jpg',
        'key' => '../../../etc/passwd',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertSuccessful();

    $publicUrl = $response->json('public_url');
    $signedUrl = $response->json('signed_url');

    expect($publicUrl)->not->toContain('..')
        ->and($publicUrl)->not->toContain('etc')
        ->and($publicUrl)->not->toContain('passwd')
        ->and($signedUrl)->not->toContain('..')
        ->and($signedUrl)->not->toContain('etc')
        ->and($signedUrl)->not->toContain('passwd')
        ->and($publicUrl)->toMatch('#^https://cdn\.bookly\.test/uploads/[a-f0-9\-]{36}\.jpg$#')
        ->and($signedUrl)->toMatch('#^https://r2\.bookly\.test/uploads/[a-f0-9\-]{36}\.jpg\?sig=#');
});

it('sets expiration within 15 minutes', function () {
    Carbon::setTestNow('2026-09-18 12:00:00');

    $partner = makePartner('approved');
    $token = $partner->user->createToken('test', ['partner'])->plainTextToken;

    $response = postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertSuccessful();

    // Compute the TTL while time is still frozen so the diff is deterministic;
    // Carbon 3's diffInMinutes returns a float (seconds precision).
    $expiresAt = Carbon::parse($response->json('expires_at'));
    $minutes = now()->diffInMinutes($expiresAt);

    Carbon::setTestNow();

    expect($minutes)->toBeGreaterThanOrEqual(14)
        ->and($minutes)->toBeLessThanOrEqual(16);
});
