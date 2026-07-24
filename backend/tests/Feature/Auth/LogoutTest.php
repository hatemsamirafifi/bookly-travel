<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth-token')->plainTextToken;

    $response = postJson('/api/public/auth/logout', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(204);

    assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});

it('returns 401 for revoked token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth-token')->plainTextToken;

    // Logout to revoke the token
    postJson('/api/public/auth/logout', [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(204);

    // Consecutive requests in a feature test share the same Application
    // instance, so the AuthManager caches the guard's resolved user. Without
    // resetting it, the second request would be authenticated by the cached
    // user instead of re-resolving the (now-deleted) bearer token. Drop the
    // cached guards so the revoked token is evaluated freshly.
    app('auth')->forgetGuards();

    // Attempt to use the revoked token
    $response = postJson('/api/public/auth/logout', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(401);
});

it('keeps other sessions active after logout', function () {
    $user = User::factory()->create();

    // Create two tokens (simulating two devices)
    $tokenA = $user->createToken('auth-token')->plainTextToken;
    $tokenB = $user->createToken('auth-token')->plainTextToken;

    // Logout from device A
    $responseA = postJson('/api/public/auth/logout', [], [
        'Authorization' => "Bearer {$tokenA}",
    ]);

    $responseA->assertStatus(204);

    // Verify token A is deleted
    $tokenARecord = PersonalAccessToken::findToken($tokenA);
    expect($tokenARecord)->toBeNull();

    // Verify token B still exists and can authenticate
    $tokenBRecord = PersonalAccessToken::findToken($tokenB);
    expect($tokenBRecord)->not()->toBeNull();

    // Token B should still be able to make authenticated requests
    $responseB = postJson('/api/public/auth/logout', [], [
        'Authorization' => "Bearer {$tokenB}",
    ]);

    $responseB->assertStatus(204);
});

it('returns 401 without token', function () {
    $response = postJson('/api/public/auth/logout');

    $response->assertStatus(401);
});
