<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'name', 'slug'];
    public function permissions() { return $this->belongsToMany(Permission::class); }
}
