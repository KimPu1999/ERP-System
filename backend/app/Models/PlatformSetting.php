<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PlatformSetting extends Model
{
    use HasUuids;

    protected $fillable = ['scope', 'setting_key', 'value', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array', 'version' => 'integer'];
    }
}
