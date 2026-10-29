<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RoleAssignment extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'role_id', 'user_id', 'company_id', 'branch_id', 'department_id'];
    public function role() { return $this->belongsTo(Role::class); }
}
