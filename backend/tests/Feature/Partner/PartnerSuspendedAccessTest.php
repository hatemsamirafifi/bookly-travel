<?php

declare(strict_types=1);

namespace Tests\Feature\Partner;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Enums\PartnerStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerSuspendedAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createSuspendedPartner(): Partner
    {
        $user = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => PartnerStatus::Suspended->value,
            'is_active' => false,
        ]);

        PartnerProfile::create([
            'partner_id' => $partner->id,
            'company_name' => 'Suspended Acme Tours',
            'contact_email' => 'acme@example.com',
            'contact_phone' => '+123456789',
            'business_description' => 'Great tours',
            'business_address' => ['street' => '123 Main St', 'city' => 'Rome', 'postal_code' => '00100', 'country' => 'IT'],
            'payout_country' => 'IT',
        ]);

        $admin = User::factory()->admin()->create();
        app(GovernanceAuditService::class)->log(
            $admin,
            'partner.suspend',
            $partner,
            ['onboarding_status' => 'approved', 'is_active' => true],
            ['onboarding_status' => 'suspended', 'is_active' => false],
            ['reason' => 'Suspended due to policy violation']
        );

        return $partner;
    }

    public function test_suspended_partner_post_tours_returns_403_blocked(): void
    {
        $partner = $this->createSuspendedPartner();
        $token = $partner->user->createToken('partner-token', ['partner'])->plainTextToken;

        Category::firstOrCreate(['slug' => 'cultural'], ['name' => 'Cultural']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/partner/tours', [
                'title' => 'Attempt Tour Creation',
                'description' => str_repeat('A valid description for this blocked tour test. ', 5),
                'category' => 'cultural',
                'destination' => 'Rome',
                'duration_value' => 2,
                'duration_unit' => 'hour',
                'difficulty_level' => 'easy',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ONBOARDING_STATUS_BLOCKED')
            ->assertJsonPath('onboarding_status', 'suspended');
    }

    public function test_suspended_partner_get_profile_returns_200(): void
    {
        $partner = $this->createSuspendedPartner();
        $token = $partner->user->createToken('partner-token', ['partner'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/partner/profile');

        $response->assertOk();
    }

    public function test_suspended_partner_get_onboarding_status_returns_200_with_reason(): void
    {
        $partner = $this->createSuspendedPartner();
        $token = $partner->user->createToken('partner-token', ['partner'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/partner/onboarding-status');

        $response->assertOk()
            ->assertJsonPath('data.onboarding_status', 'suspended')
            ->assertJsonPath('data.can_create_tours', false)
            ->assertJsonPath('data.suspension_reason', 'Suspended due to policy violation');
    }

    public function test_suspended_partner_get_notifications_returns_200(): void
    {
        $partner = $this->createSuspendedPartner();
        $token = $partner->user->createToken('partner-token', ['partner'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/partner/notifications');

        $response->assertOk();
    }

    public function test_suspended_partner_get_tours_returns_200_read_access(): void
    {
        $partner = $this->createSuspendedPartner();
        $token = $partner->user->createToken('partner-token', ['partner'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/partner/tours');

        $response->assertOk();
    }
}
