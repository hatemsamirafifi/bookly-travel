<?php

namespace App\Domains\Partner\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is a valid IBAN using the MOD 97 algorithm.
 *
 * The IBAN is considered valid when:
 * - The value is null or empty (nullable fields should pass).
 * - After removing spaces, the value matches the structural format
 *   and its checksum computes to 1 under MOD 97.
 *
 * @see https://en.wikipedia.org/wiki/International_Bank_Account_Number#Validating_the_IBAN
 */
class ValidIban implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Nullable fields: empty values are valid — let "nullable" handle presence.
        if ($value === null || $value === '') {
            return;
        }

        $iban = str_replace(' ', '', $value);

        // Basic structural check: 2-letter country code + 2 check digits + up to 30 alphanumeric chars
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            $fail('The :attribute must be a valid IBAN.');

            return;
        }

        if (! $this->mod97Check($iban)) {
            $fail('The :attribute must be a valid IBAN.');
        }
    }

    /**
     * Perform the MOD 97 checksum validation.
     *
     * Steps:
     * 1. Move the first 4 characters (country + check digits) to the end.
     * 2. Replace each letter with two digits (A=10 … Z=35).
     * 3. Compute the remainder of the resulting number divided by 97.
     * 4. The IBAN is valid when the remainder equals 1.
     */
    private function mod97Check(string $iban): bool
    {
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        $numeric = '';
        $length = strlen($rearranged);
        for ($i = 0; $i < $length; $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (ord($char) - ord('A') + 10);
            } else {
                $numeric .= $char;
            }
        }

        // Use bcmath for large numbers; fall back to modular arithmetic otherwise.
        if (function_exists('bcmod')) {
            return bcmod($numeric, '97') === '1';
        }

        // Incremental MOD 97 to avoid integer overflow on 32-bit systems.
        $remainder = 0;
        $digitLength = strlen($numeric);
        for ($i = 0; $i < $digitLength; $i++) {
            $remainder = ($remainder * 10 + (int) $numeric[$i]) % 97;
        }

        return $remainder === 1;
    }
}
