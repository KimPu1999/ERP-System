<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OperationalCostRecord extends Model
{
    use HasUuids;

    /** cost_type: actual (billed), estimate (manual projection), forecast (modelled). Currencies are never aggregated across codes. */
    protected $fillable = ['occurred_on', 'provider', 'category', 'description', 'amount', 'currency', 'cost_type', 'source', 'recorded_by'];
    protected function casts(): array { return ['occurred_on' => 'date']; }
}
