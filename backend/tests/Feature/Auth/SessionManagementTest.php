<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('lists active sessions for authenticated user', function () {
    $user = User::factory()->create();

    $tokenA = $user->createToken('auth-token')->plainTextToken;
    $tokenB = $user->createToken('mobile-app')->plainTextToken;

    $response = getJson('/api/public/account/sessions', [
        'Authorization' => "Bearer {$tokenA}",
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'last_used_at', 'is_current'],
            ],
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    // One should be marked as current
    $currentCount = collect($data)->filter(fn ($s) => $s['is_current'])->count();
    expect($currentCount)->toBe(1);
});

it('never exposes token values in session list', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth-token')->plainTextToken;

    $response = getJson('/api/public/account/sessions', [
        'Authorization' => "Bearer {$token}",
    ]);

    $json = $response->json();
    $jsonString = json_encode($json);

    expect(strpos($jsonString, $token))->toBeFalse();
});

it('returns 401 for unauthenticated session list request', function () {
    getJson('/api/public/account/sessions')->assertStatus(401);
});

it('token expires after 7 days of inactivity', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth-token')->plainTextToken;

    // Simulate token last used 8 days ago
    PersonalAccessToken::findToken($token)
        ?->update([
            'created_at' => now()->subDays(8),
            'last_used_at' => now()->subDays(8),
        ]);

    getJson('/api/public/account/sessions', [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(401);
});

it('supports multiple concurrent sessions', function () {
    $user = User::factory()->create();

    $tokens = [];
    for ($i = 0; $i < 3; $i++) {
        $tokens[] = $user->createToken("device-{$i}")->plainTextToken;
    }

    $response = getJson('/api/public/account/sessions', [
        'Authorization' => "Bearer {$tokens[0]}",
    ]);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
});
