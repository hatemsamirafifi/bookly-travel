<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerSettings;
use Illuminate\Support\Str;

class ProfileService
{
    /**
     * Get the partner's profile.
     *
     * Returns null if the profile has not been created yet.
     *
     * @param  int  $partnerId  The authenticated partner's ID
     */
    public function getProfile(int $partnerId): ?PartnerProfile
    {
        return PartnerProfile::where('partner_id', $partnerId)->first();
    }

    /**
     * Get the partner's notification settings.
     *
     * Returns null if settings have not been initialized yet.
     *
     * @param  int  $partnerId  The authenticated partner's ID
     */
    public function getSettings(int $partnerId): ?PartnerSettings
    {
        return PartnerSettings::where('partner_id', $partnerId)->first();
    }

    /**
     * Get the full profile payload including settings.
     *
     * @param  int  $partnerId  The authenticated partner's ID
     * @return array{profile: PartnerProfile|null, settings: PartnerSettings|null}
     */
    public function getProfileWithSettings(int $partnerId): array
    {
        return [
            'profile' => $this->getProfile($partnerId),
            'settings' => $this->getSettings($partnerId),
        ];
    }

    /**
     * Create or update the partner's profile.
     *
     * @param  int  $partnerId  The authenticated partner's ID
     * @param  array<string, mixed>  $data  The profile data to update
     */
    public function updateProfile(int $partnerId, array $data): PartnerProfile
    {
        return PartnerProfile::updateOrCreate(
            ['partner_id' => $partnerId],
            $data
        );
    }

    /**
     * Create or update the partner's notification settings.
     *
     * @param  int  $partnerId  The authenticated partner's ID
     * @param  array<string, mixed>  $data  The settings data to update
     */
    public function updateSettings(int $partnerId, array $data): PartnerSettings
    {
        return PartnerSettings::updateOrCreate(
            ['partner_id' => $partnerId],
            $data
        );
    }

    /**
     * Generate a presigned upload URL for a partner logo.
     *
     * Returns an array containing the signed upload URL, the public URL where the
     * uploaded image will be accessible, and the expiration timestamp.
     *
     * @param  string  $fileType  The MIME type of the file (image/jpeg or image/png)
     * @param  int  $fileSize  The file size in bytes (max 2MB for logos)
     * @return array{signed_url: string, public_url: string, expires_at: string}
     *
     * @throws \InvalidArgumentException If the file type is not supported or file size exceeds limits
     */
    public function generateLogoUploadUrl(string $fileType, int $fileSize): array
    {
        $allowedTypes = ['image/jpeg', 'image/png'];
        if (! in_array($fileType, $allowedTypes, true)) {
            throw new \InvalidArgumentException('Unsupported file type. Only JPEG and PNG are allowed.');
        }

        $maxSize = 2 * 1024 * 1024; // 2MB for logos
        if ($fileSize > $maxSize) {
            throw new \InvalidArgumentException('File size exceeds the 2MB limit for logos.');
        }

        $extension = match ($fileType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        };

        $uuid = Str::uuid()->toString();
        $path = "logos/{$uuid}.{$extension}";
        $expiresAt = now()->addMinutes(15);

        // Mock presigned URL generator — will be wired to Cloudflare R2 in production
        $signedUrl = "https://r2.bookly.test/{$path}?sig=" . Str::random(32) . '&expires=' . $expiresAt->getTimestamp();
        $publicUrl = "https://cdn.bookly.test/{$path}";

        return [
            'signed_url' => $signedUrl,
            'public_url' => $publicUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
