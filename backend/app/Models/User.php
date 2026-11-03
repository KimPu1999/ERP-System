<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasUuids, HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'email_verified_at',
        'suspended_at',
        'is_platform_super_admin',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'suspended_at' => 'datetime', 'last_login_at' => 'datetime', 'mfa_enabled_at' => 'datetime', 'mfa_recovery_codes' => 'array', 'is_platform_super_admin' => 'boolean', 'password' => 'hashed']; }
    public function tenantMemberships() { return $this->hasMany(TenantMembership::class); }
    public function roleAssignments() { return $this->hasMany(RoleAssignment::class); }
    public function companyMemberships() { return $this->hasMany(CompanyMembership::class); }
    public function staffAssignments() { return $this->hasMany(StaffAssignment::class); }
    public function invitations() { return $this->hasMany(Invitation::class, 'email', 'email'); }
    public function tenants() { return $this->belongsToMany(Tenant::class, 'tenant_memberships')->withPivot('status')->withTimestamps(); }
    public function sendPasswordResetNotification($token): void { $this->notify(new ResetPasswordNotification($token)); }
}
