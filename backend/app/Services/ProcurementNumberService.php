<?php

namespace App\Services;

use Illuminate\Support\Str;

final class ProcurementNumberService
{
    public function make(string $prefix): string { return $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)); }
}
