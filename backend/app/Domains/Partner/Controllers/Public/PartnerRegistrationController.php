<?php

namespace App\Domains\Partner\Controllers\Public;

use App\Domains\Partner\Models\Notification;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerSettings;
use App\Domains\Partner\Requests\PartnerRegistrationRequest;
use App\Mail\PartnerApplicationReceivedMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PartnerRegistrationController extends Controller
{
    /**
     * Handle partner self-registration.
     */
    public function __invoke(PartnerRegistrationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated) {
            // 1. Create User with role 'partner'
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'], // hashed cast in User model
                'role' => 'partner',
            ]);

            // 2. Create Partner with 'pending' onboarding status
            $partner = Partner::create([
                'user_id' => $user->id,
                'role' => 'partner',
                'onboarding_status' => 'pending',
                'is_active' => false,
            ]);

            // 3. Create Partner Profile
            $profile = PartnerProfile::create([
                'partner_id' => $partner->id,
                'company_name' => $validated['company_name'],
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'],
                'business_description' => $validated['business_description'],
                'business_address' => $validated['business_address'],
                'tax_id' => $validated['tax_id'] ?? null,
                'payout_country' => $validated['payout_country'],
            ]);

            // 4. Create Partner Settings
            PartnerSettings::create([
                'partner_id' => $partner->id,
            ]);

            // 5. Create Sanctum token
            $tokenResult = $user->createToken('auth-token');
            $plainTextToken = $tokenResult->plainTextToken;

            return [
                'user' => $user,
                'partner' => $partner,
                'profile' => $profile,
                'token' => $plainTextToken,
            ];
        });

        // Queue transactional email to the partner's contact_email (FR-011)
        Mail::to($result['profile']->contact_email)->queue(new PartnerApplicationReceivedMail($result['partner']));

        // Create in-app operational notification for admins with manage_partners permission (FR-013)
        $admins = User::where('role', 'admin')
            ->with('adminPermission')
            ->get()
            ->filter(function (User $admin) {
                return (bool) ($admin->adminPermission?->flags['manage_partners'] ?? false);
            });

        foreach ($admins as $admin) {
            Notification::create([
                'partner_id' => $result['partner']->id,
                'type' => 'partner_application',
                'title' => 'New Partner Application',
                'body' => "New partner application received from {$result['profile']->company_name} ({$result['profile']->contact_email}).",
                'data' => [
                    'admin_id' => $admin->id,
                    'partner_id' => $result['partner']->id,
                    'company_name' => $result['profile']->company_name,
                    'contact_email' => $result['profile']->contact_email,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'role' => $result['user']->role,
                ],
                'partner' => [
                    'id' => $result['partner']->id,
                    'onboarding_status' => $result['partner']->onboarding_status,
                    'is_active' => $result['partner']->is_active,
                ],
                'token' => $result['token'],
            ],
            'message' => 'Partner registration submitted successfully. Your account is pending admin approval.',
        ], 201);
    }
}
