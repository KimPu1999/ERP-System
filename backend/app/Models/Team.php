<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'company_id', 'name', 'code', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
