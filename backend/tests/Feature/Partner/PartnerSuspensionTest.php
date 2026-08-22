<?php

declare(strict_types=1);

namespace Tests\Feature\Partner;

use App\Domains\Admin\Actions\SuspendPartnerAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Enums\PartnerStatus;
use App\Enums\TourStatus;
use App\Mail\PartnerSuspendedMail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PartnerSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => ['manage_partners' => true]]);

        return $admin->fresh('adminPermission');
    }

    private function createPartnerWithUser(string $status = 'approved', bool $isActive = true): Partner
    {
        $user = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => $status,
            'is_active' => $isActive,
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

    public function test_admin_can_suspend_approved_partner_with_reason(): void
    {
        Mail::fake();

        $admin = $this->createAdmin();
        $partner = $this->createPartnerWithUser(PartnerStatus::Approved->value, true);

        $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Cat']);
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => 'suspension-tour-' . uniqid(),
            'location' => 'Rome, Italy',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000,
            'status' => TourStatus::Published->value,
        ]);

        $suspended = app(SuspendPartnerAction::class)->execute(
            $admin,
            $partner,
            'Repeated compliance violations with cancellation policy'
        );

        $this->assertSame(PartnerStatus::Suspended->value, $suspended->fresh()->onboarding_status);
        $this->assertFalse($suspended->fresh()->is_active);
        $this->assertSame(TourStatus::Draft->value, $tour->fresh()->status);
        $this->assertFalse($tour->fresh()->shouldBeSearchable());

        Mail::assertQueued(PartnerSuspendedMail::class, function ($mail) {
            return $mail->reason === 'Repeated compliance violations with cancellation policy';
        });

        $auditLog = GovernanceAuditLog::where('action', 'partner.suspend')
            ->where('target_id', $partner->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('Repeated compliance violations with cancellation policy', $auditLog->metadata['reason'] ?? null);
    }

    public function test_suspension_requires_reason(): void
    {
        $admin = $this->createAdmin();
        $partner = $this->createPartnerWithUser(PartnerStatus::Approved->value, true);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('A suspension reason is required.');

        app(SuspendPartnerAction::class)->execute($admin, $partner, '');
    }

    public function test_cannot_suspend_already_suspended_partner(): void
    {
        $admin = $this->createAdmin();
        $partner = $this->createPartnerWithUser(PartnerStatus::Suspended->value, false);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Partner cannot be suspended from its current state.');

        app(SuspendPartnerAction::class)->execute($admin, $partner, 'Some reason');
    }

    public function test_cannot_suspend_pending_partner(): void
    {
        $admin = $this->createAdmin();
        $partner = $this->createPartnerWithUser(PartnerStatus::Pending->value, false);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Partner cannot be suspended from its current state.');

        app(SuspendPartnerAction::class)->execute($admin, $partner, 'Some reason');
    }

    public function test_non_admin_cannot_suspend_partner(): void
    {
        $nonAdmin = User::factory()->partner()->create();
        $partner = $this->createPartnerWithUser(PartnerStatus::Approved->value, true);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('You are not authorized to suspend partners.');

        app(SuspendPartnerAction::class)->execute($nonAdmin, $partner, 'Some reason');
    }

    public function test_existing_token_rejection_after_suspension(): void
    {
        $admin = $this->createAdmin();
        $partner = $this->createPartnerWithUser(PartnerStatus::Approved->value, true);
        $partnerUser = $partner->user;

        $token = $partnerUser->createToken('partner-auth-token', ['partner'])->plainTextToken;

        $statusResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/partner/onboarding-status');
        $statusResponse->assertOk();

        app(SuspendPartnerAction::class)->execute($admin, $partner, 'Policy violation');

        Category::firstOrCreate(['slug' => 'cultural'], ['name' => 'Cultural']);

        $toursWriteResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/partner/tours', [
                'title' => 'New Tour While Suspended',
                'description' => str_repeat('A valid description for the tour test case here. ', 5),
                'category' => 'cultural',
                'destination' => 'Rome',
                'duration_value' => 2,
                'duration_unit' => 'day',
                'difficulty_level' => 'easy',
            ]);
        $toursWriteResponse->assertStatus(403);
        $this->assertSame('ONBOARDING_STATUS_BLOCKED', $toursWriteResponse->json('error_code'));

        $statusAfterSuspension = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/partner/onboarding-status');
        $statusAfterSuspension->assertOk()
            ->assertJsonPath('data.onboarding_status', 'suspended');
    }
}
