<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class DefectEvent extends Model
{
    const UPDATED_AT = null;

    use HasUuids;

    protected $fillable = ['defect_id', 'event', 'payload', 'actor_id'];

    protected function casts(): array { return ['payload' => 'array']; }
}
