<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class FeatureRequestEvent extends Model
{
    const UPDATED_AT = null;

    use HasUuids;

    protected $fillable = ['request_id', 'event', 'payload', 'actor_id'];

    protected function casts(): array { return ['payload' => 'array']; }
}
