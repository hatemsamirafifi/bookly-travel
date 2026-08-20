<?php

namespace App\Domains\Partner\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerInvitation;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CompletePartnerInvitationAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(string $token, array $data): array
    {
        return DB::transaction(function () use ($token, $data) {
            $invitation = PartnerInvitation::where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $invitation || ! $invitation->isValid()) {
                throw ValidationException::withMessages([
                    'token' => ['The invitation token is invalid or has expired.'],
                ]);
            }

            if (User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['An account with this email address already exists.'],
                ]);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'], // cast handles hashing in User model or Hash::make
                'role' => 'partner',
                'locale' => $data['locale'] ?? 'en',
            ]);

            $partner = Partner::create([
                'user_id' => $user->id,
                'role' => 'partner',
                'onboarding_status' => 'approved',
                'is_active' => true,
                'invited_by_admin' => true,
            ]);

            $partnerProfile = PartnerProfile::create([
                'partner_id' => $partner->id,
                'company_name' => $invitation->company_name,
                'contact_email' => $invitation->email,
                'contact_phone' => $data['contact_phone'] ?? null,
                'business_description' => $data['business_description'] ?? null,
                'business_address' => $data['business_address'] ?? null,
                'payout_country' => $data['payout_country'] ?? null,
            ]);

            PartnerSettings::create([
                'partner_id' => $partner->id,
            ]);

            $invitation->update([
                'status' => 'consumed',
                'consumed_at' => now(),
                'partner_id' => $partner->id,
            ]);

            $authToken = $user->createToken('partner-auth-token')->plainTextToken;

            $this->audit->log($user, 'partner.invitation_completed', $partner, [], [
                'invitation_id' => $invitation->id,
                'invited_by_admin_id' => $invitation->invited_by_admin_id,
                'company_name' => $invitation->company_name,
            ]);

            return [
                'user' => $user,
                'partner' => $partner,
                'profile' => $partnerProfile,
                'token' => $authToken,
            ];
        });
    }
}
