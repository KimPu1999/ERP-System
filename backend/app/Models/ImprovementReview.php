<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ImprovementReview extends Model
{
    use HasUuids;

    public const CADENCES = ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly'];

        public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    protected $fillable = [
        'cadence', 'title', 'findings', 'decisions', 'owner_id', 'next_action', 'priority', 'review_date', 'evidence', 'created_by',
    ];

    protected function casts(): array { return ['evidence' => 'array']; }
}
