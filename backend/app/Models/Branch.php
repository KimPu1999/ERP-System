<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasUuids;
    protected $fillable = [
        'tenant_id', 'company_id', 'name', 'code', 'branch_type', 'opening_date', 'description', 'email',
        'phone', 'alternative_phone', 'country', 'region', 'city', 'township', 'postal_code', 'street_address',
        'manager_user_id', 'business_hours', 'timezone', 'document_number_prefix', 'status', 'is_active', 'archived_at',
    ];
    protected function casts(): array { return ['is_active' => 'boolean', 'opening_date' => 'date', 'business_hours' => 'array', 'archived_at' => 'datetime']; }
    public function company() { return $this->belongsTo(Company::class); }
    public function manager() { return $this->belongsTo(User::class, 'manager_user_id'); }
    public function employees() { return $this->hasMany(Employee::class); }
    public function departments() { return $this->hasMany(Department::class); }
    public function warehouses() { return $this->hasMany(Warehouse::class); }
}
