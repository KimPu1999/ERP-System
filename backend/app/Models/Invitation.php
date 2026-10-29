<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'company_id', 'inviter_user_id', 'role_id', 'email', 'token_hash', 'expires_at', 'accepted_at'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'accepted_at' => 'datetime']; }
    public function role() { return $this->belongsTo(Role::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function company() { return $this->belongsTo(Company::class); }
}
