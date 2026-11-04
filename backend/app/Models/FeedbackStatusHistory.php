<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class FeedbackStatusHistory extends Model
{
    const UPDATED_AT = null;

    use HasUuids;

    protected $table = 'feedback_status_history';

    protected $fillable = ['tenant_id', 'feedback_id', 'from_status', 'to_status', 'reason', 'actor_id'];

    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
