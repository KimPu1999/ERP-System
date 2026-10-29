<?php
use App\Support\Decimal;

it('keeps approval threshold comparisons decimal-safe', function (): void {
    expect(Decimal::compare('1000.00', '1000'))->toBe(0)->and(Decimal::compare('1000.01', '1000'))->toBe(1);
});

it('keeps report totals deterministic for queued exports', function (): void {
    expect(Decimal::add('1250.125000', '49.875000'))->toBe('1300.000000');
});
