<?php
use App\Support\Decimal;

it('keeps subscription amounts decimal-safe', function (): void {
    expect(Decimal::add('29.990000', '0.010000'))->toBe('30.000000');
});

it('does not model payment card details in platform payment data', function (): void {
    expect(['provider_payment_id', 'status', 'amount'])->toContain('provider_payment_id')->not->toContain('card_number');
});
