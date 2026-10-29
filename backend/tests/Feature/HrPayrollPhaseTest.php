<?php
use App\Support\Decimal;

it('calculates payroll net pay with exact decimal arithmetic', function (): void {
    $gross = Decimal::add('2500.125000', '125.375000');
    $net = Decimal::sub($gross, '75.500000');
    expect($gross)->toBe('2625.500000')->and($net)->toBe('2550.000000');
});

it('does not use floating point for payroll rounding', function (): void {
    expect(Decimal::div(Decimal::mul('1999.99', '7.5'), '100'))->toBe('149.999250');
});
