<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'company_id', 'branch_id', 'parent_department_id', 'manager_user_id', 'name', 'code', 'description', 'status'];
    public function company() { return $this->belongsTo(Company::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_department_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_department_id'); }
    public function manager() { return $this->belongsTo(User::class, 'manager_user_id'); }
    public function employees() { return $this->hasMany(Employee::class); }
}
