<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ReleaseRecord extends Model
{
    use HasUuids;

    protected $fillable = ['version', 'environment', 'status', 'release_notes', 'known_issues', 'rollback_reference', 'deployed_at', 'recorded_by'];
    protected function casts(): array { return ['deployed_at' => 'datetime']; }
}
