<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SecuritySetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value', 'updated_by'];
    protected function casts(): array { return ['value' => 'array']; }
}
