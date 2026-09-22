<?php

use App\Domains\Payment\Services\ApplicationFeeCalculator;

it('calculates application fees using integer minor units', function (int $amount, string $percentage, int $expected) {
    expect((new ApplicationFeeCalculator)->calculate($amount, $percentage))->toBe($expected);
})->with([
    'fifteen percent' => [20000, '15.00', 3000],
    'decimal percentage' => [999, '12.34', 123],
    'round half up' => [1, '50.00', 1],
    'zero amount' => [0, '15.00', 0],
]);

it('rejects invalid commission percentages', function (string $percentage) {
    (new ApplicationFeeCalculator)->calculate(10000, $percentage);
})->with(['100.01', '-1', '15.001', 'not-a-number'])
    ->throws(InvalidArgumentException::class);

it('rejects negative minor-unit amounts', function () {
    (new ApplicationFeeCalculator)->calculate(-1, '15.00');
})->throws(InvalidArgumentException::class);
