<?php

declare(strict_types=1);

namespace Tests\Feature\Partner;

use App\Domains\Admin\Actions\ReinstatePartnerAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Enums\PartnerStatus;
use App\Enums\TourStatus;
use App\Mail\PartnerReinstatedMail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PartnerReinstatementTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => ['manage_partners' => true]]);

        return $admin->fresh('adminPermission');
    }

    private function createSuspendedPartner(): Partner
    {
        $user = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => PartnerStatus::Suspended->value,
            'is_active' => false,
            'suspended_at' => now()->subDays(2),
            'suspension_reason' => 'Violation of terms',
        ]);

        PartnerProfile::create([
            'partner_id' => $partner->id,
            'company_name' => 'Acme Tours ' . uniqid(),
            'contact_email' => 'contact@acme.com',
            'contact_phone' => '+123456789',
            'business_description' => 'Test business description',
            'business_address' => ['street' => '123 Main St', 'city' => 'Rome', 'postal_code' => '00100', 'country' => 'IT'],
            'payout_country' => 'IT',
        ]);

        return $partner;
    }

    public function test_admin_can_reinstate_suspended_partner(): void
    {
        Mail::fake();

        $admin = $this->createAdmin();
        $partner = $this->createSuspendedPartner();

        $reinstated = app(ReinstatePartnerAction::class)->execute($admin, $partner);

        $this->assertSame(PartnerStatus::Approved->value, $reinstated->onboarding_status);
        $this->assertTrue($reinstated->is_active);

        Mail::assertQueued(PartnerReinstatedMail::class, function ($mail) use ($partner) {
            return $mail->hasTo($partner->user->email);
        });

        $auditLog = GovernanceAuditLog::where('action', 'partner.reinstate')
            ->where('target_id', $partner->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($admin->id, $auditLog->actor_id);
    }

    public function test_reinstated_partner_tours_remain_draft(): void
    {
        $admin = $this->createAdmin();
        $partner = $this->createSuspendedPartner();

        $category = Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure']);
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => 'reinstated-tour-' . uniqid(),
            'location' => 'Rome, Italy',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000,
            'status' => TourStatus::Draft->value,
        ]);

        app(ReinstatePartnerAction::class)->execute($admin, $partner);

        $tour->refresh();
        $this->assertSame(TourStatus::Draft->value, $tour->status);
    }

    public function test_cannot_reinstate_non_suspended_partner(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => PartnerStatus::Approved->value,
            'is_active' => true,
        ]);

        $this->expectException(HttpException::class);
        app(ReinstatePartnerAction::class)->execute($admin, $partner);
    }

    public function test_reinstated_partner_can_create_new_tours(): void
    {
        $admin = $this->createAdmin();
        $partner = $this->createSuspendedPartner();
        $partnerUser = $partner->user;

        $token = $partnerUser->createToken('partner-token', ['partner'])->plainTextToken;

        app(ReinstatePartnerAction::class)->execute($admin, $partner);

        Category::firstOrCreate(['slug' => 'cultural'], ['name' => 'Cultural']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/partner/tours', [
                'title' => 'Brand New Tour After Reinstatement',
                'description' => str_repeat('A valid description for this reinstated tour. ', 5),
                'category' => 'cultural',
                'destination' => 'Rome',
                'duration_value' => 3,
                'duration_unit' => 'hour',
                'difficulty_level' => 'easy',
            ]);

        $response->assertStatus(201);
    }

    public function test_reinstated_partner_old_tours_not_in_search(): void
    {
        $admin = $this->createAdmin();
        $partner = $this->createSuspendedPartner();

        $category = Category::firstOrCreate(['slug' => 'sightseeing'], ['name' => 'Sightseeing']);
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => 'draft-search-test-' . uniqid(),
            'location' => 'Rome, Italy',
            'duration_minutes' => 180,
            'duration_label' => '3 hours',
            'group_size_min' => 1,
            'group_size_max' => 15,
            'price_amount' => 7500,
            'status' => TourStatus::Draft->value,
        ]);

        app(ReinstatePartnerAction::class)->execute($admin, $partner);

        $tour->refresh();
        $this->assertFalse($tour->shouldBeSearchable());
        $this->assertSame(TourStatus::Draft->value, $tour->status);
    }
}
