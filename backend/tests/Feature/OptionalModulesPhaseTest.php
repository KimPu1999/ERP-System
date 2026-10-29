<?php
use App\Support\Decimal;

it('calculates a POS tax total without floating point arithmetic', function (): void {
    $subtotal = Decimal::mul('2', '19.99');
    $tax = Decimal::div(Decimal::mul($subtotal, '5'), '100');
    expect($subtotal)->toBe('39.980000')->and($tax)->toBe('1.999000');
});

it('calculates straight-line monthly depreciation with decimals', function (): void {
    $monthly = Decimal::div(Decimal::sub('12000', '2000'), '40');
    expect($monthly)->toBe('250.000000');
});
