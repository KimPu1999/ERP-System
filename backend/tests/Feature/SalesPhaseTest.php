<?php
use App\Services\SalesCalculationService;
use App\Support\Decimal;

it('calculates sales tax and discount using decimal arithmetic', function (): void {
    $line = app(SalesCalculationService::class)->line(['quantity'=>'3','unit_price'=>'19.99','discount_rate'=>'10','tax_rate'=>'5']);
    expect($line['line_subtotal'])->toBe('59.970000')->and($line['discount_amount'])->toBe('5.997000')->and($line['tax_amount'])->toBe('2.698650')->and($line['line_total'])->toBe('56.671650');
});

it('never uses binary floating point for money helpers', function (): void {
    expect(Decimal::add('0.10','0.20'))->toBe('0.300000');
});
