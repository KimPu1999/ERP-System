<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SupportSlaPolicy extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'priority', 'first_response_minutes', 'resolution_minutes', 'business_hours', 'is_active'];
    protected function casts(): array { return ['business_hours' => 'array']; }
}
