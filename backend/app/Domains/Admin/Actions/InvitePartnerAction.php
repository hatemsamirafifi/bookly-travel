<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\PartnerInvitation;
use App\Mail\PartnerInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitePartnerAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(string $email, string $companyName, User $admin, string $locale = 'en'): PartnerInvitation
    {
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email address is already registered.'],
            ]);
        }

        if (PartnerInvitation::where('email', $email)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An active invitation for this email address already exists.'],
            ]);
        }

        $token = Str::random(64);

        $invitation = PartnerInvitation::create([
            'email' => $email,
            'company_name' => $companyName,
            'token' => $token,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'invited_by_admin_id' => $admin->id,
        ]);

        Mail::to($email)->locale($locale)->queue(new PartnerInvitationMail(
            $invitation,
            $admin->name ?? 'Administrator',
            $locale
        ));

        $this->audit->log($admin, 'partner.invite', $invitation, [], [
            'email' => $email,
            'company_name' => $companyName,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'locale' => $locale,
        ]);

        return $invitation;
    }
}
