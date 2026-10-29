<?php

namespace App\Support;

use InvalidArgumentException;

final class Decimal
{
    public static function normalize(string|int $value, int $scale = 6): string
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', (string) $value)) throw new InvalidArgumentException('Invalid decimal value.');
        return bcadd((string) $value, '0', $scale);
    }
    public static function add(string $left, string $right, int $scale = 6): string { return bcadd($left, $right, $scale); }
    public static function sub(string $left, string $right, int $scale = 6): string { return bcsub($left, $right, $scale); }
    public static function mul(string $left, string $right, int $scale = 6): string { return bcmul($left, $right, $scale); }
    public static function div(string $left, string $right, int $scale = 6): string { if (bccomp($right, '0', $scale) === 0) throw new InvalidArgumentException('Division by zero.'); return bcdiv($left, $right, $scale); }
    public static function compare(string $left, string $right, int $scale = 6): int { return bccomp($left, $right, $scale); }
}
