<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\postJson;

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
