<?php

declare(strict_types=1);

namespace Tests\Feature\Partner;

use App\Models\User;
use App\Domains\Partner\Models\PartnerInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpirePartnerInvitationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_invitations_command_expires_pending_invitations_older_than_expiration_window(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Expired pending invitation (created 8 days ago, expires_at in the past)
        $expiredPending = PartnerInvitation::create([
            'email' => 'expired@example.com',
            'company_name' => 'Old Expired Co',
            'token' => str_repeat('a', 64),
            'status' => 'pending',
            'expires_at' => Carbon::now()->subDay(),
            'invited_by_admin_id' => $admin->id,
        ]);

        // Valid pending invitation (expires in future)
        $validPending = PartnerInvitation::create([
            'email' => 'valid@example.com',
            'company_name' => 'Valid Active Co',
            'token' => str_repeat('b', 64),
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(3),
            'invited_by_admin_id' => $admin->id,
        ]);

        // Already accepted invitation (should remain accepted)
        $accepted = PartnerInvitation::create([
            'email' => 'accepted@example.com',
            'company_name' => 'Accepted Co',
            'token' => str_repeat('c', 64),
            'status' => 'accepted',
            'expires_at' => Carbon::now()->subDay(),
            'accepted_at' => Carbon::now()->subDays(2),
            'invited_by_admin_id' => $admin->id,
        ]);

        $this->artisan('partner-invitations:expire')
            ->expectsOutputToContain('Expired 1 partner invitation(s).')
            ->assertSuccessful();

        $this->assertSame('expired', $expiredPending->fresh()->status);
        $this->assertSame('pending', $validPending->fresh()->status);
        $this->assertSame('accepted', $accepted->fresh()->status);
    }
}
