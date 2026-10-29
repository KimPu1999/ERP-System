<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompanyMembership extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'company_id', 'user_id', 'status', 'joined_at', 'suspended_at', 'revoked_at'];
    protected function casts(): array { return ['joined_at' => 'datetime', 'suspended_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function company() { return $this->belongsTo(Company::class); }
    public function user() { return $this->belongsTo(User::class); }
}
