<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SatisfactionResponse extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'context', 'context_id', 'score', 'comment', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'score' => 'integer'];
    }
}
