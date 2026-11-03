<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PlatformBrandingAsset extends Model
{
    use HasUuids;

    protected $fillable = ['asset_key', 'path', 'mime_type', 'size_bytes', 'sha256', 'updated_by'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }
}
