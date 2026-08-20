<?php

use App\Domains\Partner\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks non-approved partner accessing protected partner routes', function (string $status) {
    $user = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => $status,
        'is_active' => $status === 'approved',
    ]);
    $partner->profile()->create(['company_name' => 'Demo', 'contact_email' => 'demo@example.com']);

    $token = $user->createToken('test', ['partner'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/partner/tours', [
            'title' => 'Sample Tour',
            'category' => 'cultural',
            'destination' => 'Rome',
            'duration_value' => 2,
            'duration_unit' => 'hour',
            'difficulty_level' => 'easy',
        ]);

    $response->assertStatus(403);
    $response->assertJsonFragment(['error_code' => 'ONBOARDING_STATUS_BLOCKED']);
})->with(['pending', 'rejected', 'suspended']);

it('allows approved partner to access protected partner routes', function () {
    $user = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $partner->profile()->create(['company_name' => 'Demo', 'contact_email' => 'demo@example.com']);

    $token = $user->createToken('test', ['partner'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/partner/tours');

    expect($response->status())->not->toBe(403);
});

it('allows non-approved partner to access onboarding status endpoint', function (string $status) {
    $user = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => $status,
        'is_active' => false,
    ]);
    $partner->profile()->create(['company_name' => 'Demo', 'contact_email' => 'demo@example.com']);

    $token = $user->createToken('test', ['partner'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/partner/onboarding/status');

    $response->assertOk();
    $response->assertJsonPath('data.onboarding_status', $status);
})->with(['pending', 'rejected', 'suspended']);
