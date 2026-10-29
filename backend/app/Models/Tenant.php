<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasUuids, HasFactory;
    protected $fillable = ['name', 'slug'];
    public function users() { return $this->belongsToMany(User::class, 'tenant_memberships')->withPivot('status')->withTimestamps(); }
    public function memberships() { return $this->hasMany(TenantMembership::class); }
    public function companies() { return $this->hasMany(Company::class); }
}
