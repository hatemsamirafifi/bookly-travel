<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController
{
    public function show(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $profile = PartnerProfile::where('partner_id', $partnerId)->first();
        $settings = PartnerSettings::where('partner_id', $partnerId)->first();

        return response()->json([
            'profile' => $profile,
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'business_description' => 'nullable|string|max:5000',
            'logo_url' => 'nullable|string|url|max:2048',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|url|max:2048',
            'business_address' => 'nullable|array',
            'tax_id' => 'nullable|string|max:100',
            'payout_holder_name' => 'nullable|string|max:255',
            'payout_bank_name' => 'nullable|string|max:255',
            'payout_account_number' => 'nullable|string|max:100',
            'payout_iban' => 'nullable|string|max:34',
            'payout_swift_bic' => 'nullable|string|max:11',
            'payout_country' => 'nullable|string|size:2',
        ]);

        $profile = PartnerProfile::updateOrCreate(
            ['partner_id' => $partnerId],
            $data
        );

        return response()->json($profile);
    }

    public function settings(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $settings = PartnerSettings::where('partner_id', $partnerId)->first();

        return response()->json($settings);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validate([
            'notify_new_booking' => 'sometimes|boolean',
            'notify_cancellation' => 'sometimes|boolean',
            'notify_daily_summary' => 'sometimes|boolean',
            'notify_review_received' => 'sometimes|boolean',
            'notify_tour_status_change' => 'sometimes|boolean',
            'locale' => 'sometimes|string|size:2',
        ]);

        $settings = PartnerSettings::updateOrCreate(
            ['partner_id' => $partnerId],
            $data
        );

        return response()->json($settings);
    }
}
