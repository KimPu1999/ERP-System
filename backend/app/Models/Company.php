<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Company extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'name', 'code', 'timezone', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function branches() { return $this->hasMany(Branch::class); }
}
