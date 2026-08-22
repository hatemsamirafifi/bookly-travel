<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerSettings;
use App\Domains\Partner\Requests\UpdateProfileRequest;
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

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validated();

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
