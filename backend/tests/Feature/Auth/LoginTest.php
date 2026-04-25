<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\assertDatabaseHas;

it('signs in a traveler with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'traveler@example.com',
        'password' => Hash::make('Password123!'),
        'last_login_at' => null,
    ]);

    postJson('/api/public/auth/login', [
        'email' => 'traveler@example.com',
        'password' => 'Password123!',
    ])
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data.user.id')
            ->where('data.user.email', 'traveler@example.com')
            ->has('data.token')
        );

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();

    assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_success',
    ]);
});

it('fails with generic error for non-existent email', function () {
    postJson('/api/public/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'Password123!',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid email or password.')
        ->assertJsonPath('code', 'invalid_credentials');
});

it('fails with generic error for wrong password', function () {
    $user = User::factory()->create([
        'email' => 'traveler@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    postJson('/api/public/auth/login', [
        'email' => 'traveler@example.com',
        'password' => 'WrongPassword123!',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid email or password.')
        ->assertJsonPath('code', 'invalid_credentials');
});

it('fails validation with empty fields', function () {
    postJson('/api/public/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('normalizes email with whitespace and uppercase', function () {
    User::factory()->create([
        'email' => 'upper@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    postJson('/api/public/auth/login', [
        'email' => '  UPPER@Example.COM  ',
        'password' => 'Password123!',
    ])
        ->assertOk();
});

it('fails for account with no password', function () {
    $user = User::factory()->create([
        'email' => 'nopass@example.com',
        'password' => null,
    ]);

    postJson('/api/public/auth/login', [
        'email' => 'nopass@example.com',
        'password' => 'SomePassword123!',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid email or password.')
        ->assertJsonPath('code', 'invalid_credentials');
});

it('returns structured error codes', function () {
    $user = User::factory()->create([
        'email' => 'locked@example.com',
        'password' => Hash::make('Password123!'),
        'locked_until' => now()->addMinutes(10),
    ]);

    postJson('/api/public/auth/login', [
        'email' => 'locked@example.com',
        'password' => 'Password123!',
    ])
        ->assertStatus(423)
        ->assertJsonPath('code', 'account_locked');
});

it('locks account after 5 failed attempts with 1 minute tier', function () {
    \ = User::factory()->create([
        'email' => 'brute@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    for (\ = 0; \ < 5; \++) {
        postJson('/api/public/auth/login', [
            'email' => 'brute@example.com',
            'password' => 'WrongPassword!',
        ])->assertStatus(422);
    }

    postJson('/api/public/auth/login', [
        'email' => 'brute@example.com',
        'password' => 'Password123!',
    ])
        ->assertStatus(423)
        ->assertJsonPath('code', 'account_locked');

    \->refresh();
    expect(\->failed_login_count)->toBe(5);
    expect(\->locked_until)->not->toBeNull();
    
    assertDatabaseHas('auth_audit_logs', [
        'user_id' => \->id,
        'event_type' => 'account_lockout',
    ]);
});

it('rejects login while account is locked even with correct password', function () {
    \ = User::factory()->create([
        'email' => 'locked2@example.com',
        'password' => Hash::make('Password123!'),
        'locked_until' => now()->addMinutes(1),
    ]);

    postJson('/api/public/auth/login', [
        'email' => 'locked2@example.com',
        'password' => 'Password123!',
    ])->assertStatus(423);
});

it('resets counter and tier on successful login after lockout expires', function () {
    \ = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('Password123!'),
        'locked_until' => now()->subMinutes(1),
        'failed_login_count' => 5,
    ]);

    postJson('/api/public/auth/login', [
        'email' => 'reset@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    \->refresh();
    expect(\->failed_login_count)->toBe(0);
    expect(\->locked_until)->toBeNull();

    for (\ = 0; \ < 5; \++) {
        postJson('/api/public/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'WrongPassword!',
        ]);
    }

    \->refresh();
    // 1st lockout after a successful login should be 1 minute
    \ = now()->diffInMinutes(\->locked_until);
    expect(\)->toBeLessThanOrEqual(1);
});

it('escalates to 30 minute tier on third lockout', function () {
    \ = User::factory()->create([
        'email' => 'escalate@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    // 1st lockout
    for (\ = 0; \ < 5; \++) {
        postJson('/api/public/auth/login', ['email' => 'escalate@example.com', 'password' => 'Wrong!']);
    }
    \->refresh();
    expect(now()->diffInMinutes(\->locked_until))->toBeLessThanOrEqual(1);

    // Expire 1st lockout
    \->locked_until = now()->subMinutes(1);
    \->save();

    // 2nd lockout
    for (\ = 0; \ < 5; \++) {
        postJson('/api/public/auth/login', ['email' => 'escalate@example.com', 'password' => 'Wrong!']);
    }
    \->refresh();
    expect(now()->diffInMinutes(\->locked_until))->toBeGreaterThan(1);
    expect(now()->diffInMinutes(\->locked_until))->toBeLessThanOrEqual(5);

    // Expire 2nd lockout
    \->locked_until = now()->subMinutes(1);
    \->save();

    // 3rd lockout
    for (\ = 0; \ < 5; \++) {
        postJson('/api/public/auth/login', ['email' => 'escalate@example.com', 'password' => 'Wrong!']);
    }
    \->refresh();
    expect(now()->diffInMinutes(\->locked_until))->toBeGreaterThan(5);
    expect(now()->diffInMinutes(\->locked_until))->toBeLessThanOrEqual(30);
});

it('resets failed count on successful login before reaching 5', function () {
    \ = User::factory()->create([
        'email' => 'reset_early@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    postJson('/api/public/auth/login', ['email' => 'reset_early@example.com', 'password' => 'Wrong!']);
    postJson('/api/public/auth/login', ['email' => 'reset_early@example.com', 'password' => 'Wrong!']);
    
    postJson('/api/public/auth/login', [
        'email' => 'reset_early@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    \->refresh();
    expect(\->failed_login_count)->toBe(0);
});

it('handles concurrent failed login requests safely', function () {
    \ = User::factory()->create([
        'email' => 'concurrent@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    postJson('/api/public/auth/login', ['email' => 'concurrent@example.com', 'password' => 'Wrong!']);
    postJson('/api/public/auth/login', ['email' => 'concurrent@example.com', 'password' => 'Wrong!']);
    
    \->refresh();
    expect(\->failed_login_count)->toBe(2);
});

it('survives redis cache flush during lockout', function () {
    \ = User::factory()->create([
        'email' => 'redis@example.com',
        'password' => Hash::make('Password123!'),
        'locked_until' => now()->addMinutes(10),
    ]);

    // Simulating cache flush: Laravel's cache clear doesn't affect DB
    \Illuminate\Support\Facades\Cache::flush();

    postJson('/api/public/auth/login', [
        'email' => 'redis@example.com',
        'password' => 'Password123!',
    ])->assertStatus(423);
    
    assertDatabaseHas('users', [
        'id' => \->id,
    ]);
    \->refresh();
    expect(\->locked_until)->not->toBeNull();
});


it('responds within 3 seconds', function () {
    \ = User::factory()->create([
        'email' => 'perf@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    \ = microtime(true);
    
    postJson('/api/public/auth/login', [
        'email' => 'perf@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    \ = (microtime(true) - \) * 1000;
    
    expect(\)->toBeLessThan(3000);
})->group('performance');

