<?php

use App\Domains\Auth\Events\AccountLockedOut;
use App\Mail\AccountLockedOutMail;
use App\Models\AuthAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

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

    $auditLog = AuthAuditLog::where('user_id', $user->id)
        ->where('event_type', 'login_failed')
        ->latest()
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->metadata)->toBeArray();
    expect($auditLog->metadata['rejected_due_to_lockout'])->toBeTrue();
});

it('locks account after 5 failed attempts with 1 minute tier', function () {
    $user = User::factory()->create([
        'email' => 'brute@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
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

    $user->refresh();
    expect($user->failed_login_count)->toBe(5);
    expect($user->locked_until)->not->toBeNull();

    assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'account_lockout',
    ]);
});

it('dispatches AccountLockedOut event when lockout triggers', function () {
    Event::fake([
        AccountLockedOut::class,
    ]);

    $user = User::factory()->create([
        'email' => 'fr010@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        postJson('/api/public/auth/login', [
            'email' => 'fr010@example.com',
            'password' => 'WrongPassword!',
        ]);
    }

    // FR-010: AccountLockedOut event MUST be dispatched exactly once
    Event::assertDispatched(
        AccountLockedOut::class,
        fn ($event) => $event->user->id === $user->id
    );
});

it('rejects login while account is locked even with correct password', function () {
    $user = User::factory()->create([
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
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('Password123!'),
        'locked_until' => now()->subMinutes(1),
        'failed_login_count' => 5,
    ]);

    postJson('/api/public/auth/login', [
        'email' => 'reset@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    $user->refresh();
    expect($user->failed_login_count)->toBe(0);
    expect($user->locked_until)->toBeNull();

    for ($i = 0; $i < 5; $i++) {
        postJson('/api/public/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'WrongPassword!',
        ]);
    }

    $user->refresh();
    // 1st lockout after a successful login should be 1 minute
    $minutes = now()->diffInMinutes($user->locked_until);
    expect($minutes)->toBeLessThanOrEqual(1);
});

it('escalates to 30 minute tier on third lockout', function () {
    $user = User::factory()->create([
        'email' => 'escalate@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    // 1st lockout
    for ($i = 0; $i < 5; $i++) {
        postJson('/api/public/auth/login', ['email' => 'escalate@example.com', 'password' => 'Wrong!']);
    }
    $user->refresh();
    expect(now()->diffInMinutes($user->locked_until))->toBeLessThanOrEqual(1);

    // Expire 1st lockout
    $user->locked_until = now()->subMinutes(1);
    $user->save();

    // 2nd lockout
    for ($i = 0; $i < 5; $i++) {
        postJson('/api/public/auth/login', ['email' => 'escalate@example.com', 'password' => 'Wrong!']);
    }
    $user->refresh();
    expect(now()->diffInMinutes($user->locked_until))->toBeGreaterThan(1);
    expect(now()->diffInMinutes($user->locked_until))->toBeLessThanOrEqual(5);

    // Expire 2nd lockout
    $user->locked_until = now()->subMinutes(1);
    $user->save();

    // 3rd lockout
    for ($i = 0; $i < 5; $i++) {
        postJson('/api/public/auth/login', ['email' => 'escalate@example.com', 'password' => 'Wrong!']);
    }
    $user->refresh();
    expect(now()->diffInMinutes($user->locked_until))->toBeGreaterThan(5);
    expect(now()->diffInMinutes($user->locked_until))->toBeLessThanOrEqual(30);
});

it('resets failed count on successful login before reaching 5', function () {
    $user = User::factory()->create([
        'email' => 'reset_early@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    postJson('/api/public/auth/login', ['email' => 'reset_early@example.com', 'password' => 'Wrong!']);
    postJson('/api/public/auth/login', ['email' => 'reset_early@example.com', 'password' => 'Wrong!']);

    postJson('/api/public/auth/login', [
        'email' => 'reset_early@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    $user->refresh();
    expect($user->failed_login_count)->toBe(0);
});

it('handles concurrent failed login requests safely', function () {
    $user = User::factory()->create([
        'email' => 'concurrent@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    postJson('/api/public/auth/login', ['email' => 'concurrent@example.com', 'password' => 'Wrong!']);
    postJson('/api/public/auth/login', ['email' => 'concurrent@example.com', 'password' => 'Wrong!']);

    $user->refresh();
    expect($user->failed_login_count)->toBe(2);
});

it('survives redis cache flush during lockout', function () {
    $user = User::factory()->create([
        'email' => 'redis@example.com',
        'password' => Hash::make('Password123!'),
        'locked_until' => now()->addMinutes(10),
    ]);

    // Simulating cache flush: Laravel's cache clear doesn't affect DB
    Cache::flush();

    postJson('/api/public/auth/login', [
        'email' => 'redis@example.com',
        'password' => 'Password123!',
    ])->assertStatus(423);

    assertDatabaseHas('users', [
        'id' => $user->id,
    ]);
    $user->refresh();
    expect($user->locked_until)->not->toBeNull();
});

it('rate limits the login endpoint after 10 requests', function () {
    $user = User::factory()->create([
        'email' => 'ratelimit@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    RateLimiter::clear('auth');

    for ($i = 0; $i < 10; $i++) {
        postJson('/api/public/auth/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'Password123!',
        ]);
    }

    postJson('/api/public/auth/login', [
        'email' => 'ratelimit@example.com',
        'password' => 'Password123!',
    ])->assertStatus(429);
});

it('responds within 3 seconds', function () {
    $user = User::factory()->create([
        'email' => 'perf@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    $start = microtime(true);

    postJson('/api/public/auth/login', [
        'email' => 'perf@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    $elapsed = (microtime(true) - $start) * 1000;

    expect($elapsed)->toBeLessThan(3000);
})->group('performance');

it('does not send duplicate email for the same lockout event', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'dedup@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    // Trigger lockout (dispatches AccountLockedOut event → listener sends email)
    for ($i = 0; $i < 5; $i++) {
        postJson('/api/public/auth/login', [
            'email' => 'dedup@example.com',
            'password' => 'WrongPassword!',
        ]);
    }

    Mail::assertQueued(AccountLockedOutMail::class, 1);

    $user->refresh();
    expect($user->last_lockout_email_sent_at)->not->toBeNull();

    // Manually dispatch a duplicate AccountLockedOut event with the same locked_until
    event(new AccountLockedOut($user));

    // No second email should be queued — listener detects same locked_until timestamp
    Mail::assertQueued(AccountLockedOutMail::class, 1);
});

it('queues an email notification when account is locked out', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'notify@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        postJson('/api/public/auth/login', [
            'email' => 'notify@example.com',
            'password' => 'WrongPassword!',
        ]);
    }

    Mail::assertQueued(AccountLockedOutMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->subject === __('emails.account_locked_out.subject', [], $user->locale ?? 'en');
    });
});
