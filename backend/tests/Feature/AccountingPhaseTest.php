<?php
use App\Services\AccountingService;
use App\Support\Decimal;

it('rejects an unbalanced journal before posting', function (): void {
    expect(Decimal::compare('100.000000', '99.990000'))->not->toBe(0);
});

it('preserves exact currency rounding', function (): void {
    expect(Decimal::mul('10.25', '1.125'))->toBe('11.531250');
});
