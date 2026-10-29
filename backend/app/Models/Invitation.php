<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'company_id', 'inviter_user_id', 'accepted_by_user_id', 'role_id', 'branch_id', 'department_id', 'team_id', 'email', 'personal_message', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'status'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function role() { return $this->belongsTo(Role::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function company() { return $this->belongsTo(Company::class); }
    public function inviter() { return $this->belongsTo(User::class, 'inviter_user_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function team() { return $this->belongsTo(Team::class); }
}
