<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasUuids, HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'status'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'suspended_at' => 'datetime', 'mfa_enabled_at' => 'datetime', 'mfa_recovery_codes' => 'array', 'password' => 'hashed']; }
    public function tenantMemberships() { return $this->hasMany(TenantMembership::class); }
    public function tenants() { return $this->belongsToMany(Tenant::class, 'tenant_memberships')->withPivot('status')->withTimestamps(); }
}
