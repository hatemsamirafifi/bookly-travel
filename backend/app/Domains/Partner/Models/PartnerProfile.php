<?php

namespace App\Domains\Partner\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PartnerProfile extends Model
{
    protected $fillable = [
        'partner_id',
        'company_name',
        'business_description',
        'logo_url',
        'contact_email',
        'contact_phone',
        'website',
        'business_address',
        'tax_id',
        'payout_holder_name',
        'payout_bank_name',
        'payout_account_number',
        'payout_iban',
        'payout_swift_bic',
        'payout_country',
    ];

    protected function casts(): array
    {
        return [
            'business_address' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Encrypt payout account number at rest.
     * Returns masked value (last 4 digits) when reading, encrypts when writing.
     */
    protected function payoutAccountNumber(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value
                ? $this->decryptAndMask($value, 'account_number')
                : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Encrypt payout IBAN at rest.
     * Returns masked value (last 4 characters) when reading, encrypts when writing.
     */
    protected function payoutIban(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value
                ? $this->decryptAndMask($value, 'iban')
                : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Encrypt payout SWIFT/BIC at rest.
     * Returns masked value (last 3 characters) when reading, encrypts when writing.
     */
    protected function payoutSwiftBic(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value
                ? $this->decryptAndMask($value, 'swift_bic')
                : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Decrypt a value and return a masked version for display.
     * Original values are accessible via getRawPayoutAttribute() methods for API responses.
     */
    private function decryptAndMask(string $encrypted, string $type): string
    {
        try {
            $decrypted = Crypt::decryptString($encrypted);

            return match ($type) {
                'account_number' => '****' . substr($decrypted, -4),
                'iban' => str_repeat('*', strlen($decrypted) - 4) . substr($decrypted, -4),
                'swift_bic' => substr($decrypted, 0, 3) . '***',
                default => $decrypted,
            };
        } catch (\Exception) {
            // If decryption fails, the value might be stored in plain text (pre-migration)
            // Return masked version of plain text
            return match ($type) {
                'account_number' => '****' . substr($encrypted, -4),
                'iban' => str_repeat('*', strlen($encrypted) - 4) . substr($encrypted, -4),
                'swift_bic' => substr($encrypted, 0, 3) . '***',
                default => $encrypted,
            };
        }
    }

    /**
     * Get the raw (decrypted) payout account number for processing.
     * Use only when the full value is needed (e.g., payment processing).
     */
    public function getRawPayoutAccountNumber(): ?string
    {
        $value = $this->getRawOriginal('payout_account_number');
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return $value;
        }
    }

    /**
     * Get the raw (decrypted) payout IBAN for processing.
     */
    public function getRawPayoutIban(): ?string
    {
        $value = $this->getRawOriginal('payout_iban');
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return $value;
        }
    }

    /**
     * Get the raw (decrypted) payout SWIFT/BIC for processing.
     */
    public function getRawPayoutSwiftBic(): ?string
    {
        $value = $this->getRawOriginal('payout_swift_bic');
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return $value;
        }
    }
}