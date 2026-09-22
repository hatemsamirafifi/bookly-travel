<?php

namespace App\Domains\Payment\Services;

use InvalidArgumentException;

class ApplicationFeeCalculator
{
    private const BASIS_POINTS_PER_PERCENT = 100;

    private const BASIS_POINTS_PER_WHOLE = 10000;

    /**
     * Calculate a fee from integer minor units without binary floating point.
     * Percentages support at most two decimal places and are converted to
     * basis points before applying half-up rounding.
     */
    public function calculate(int $amountMinor, string $percentage): int
    {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('The amount must be a non-negative minor-unit integer.');
        }

        $basisPoints = $this->toBasisPoints($percentage);

        if ($basisPoints !== 0 && $amountMinor > intdiv(PHP_INT_MAX - 5000, $basisPoints)) {
            throw new InvalidArgumentException('The amount is too large to calculate safely.');
        }

        return intdiv(($amountMinor * $basisPoints) + 5000, self::BASIS_POINTS_PER_WHOLE);
    }

    private function toBasisPoints(string $percentage): int
    {
        $percentage = trim($percentage);

        if (! preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $percentage, $matches)) {
            throw new InvalidArgumentException('The commission percentage must be between 0 and 100 with at most two decimal places.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        $basisPoints = ($whole * self::BASIS_POINTS_PER_PERCENT) + (int) $fraction;

        if ($basisPoints > self::BASIS_POINTS_PER_WHOLE) {
            throw new InvalidArgumentException('The commission percentage cannot exceed 100.');
        }

        return $basisPoints;
    }
}
