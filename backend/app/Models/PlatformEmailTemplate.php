<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PlatformEmailTemplate extends Model
{
    use HasUuids;

    protected $fillable = ['template_key', 'locale', 'subject', 'body', 'status', 'version', 'published_at', 'updated_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'published_at' => 'datetime'];
    }
}
