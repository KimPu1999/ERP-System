<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class FeedbackNote extends Model
{
    const UPDATED_AT = null;

    use HasUuids;

    protected $fillable = ['tenant_id', 'feedback_id', 'author_id', 'author_type', 'visibility', 'body'];

    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
