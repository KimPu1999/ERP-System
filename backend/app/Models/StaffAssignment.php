<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StaffAssignment extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'company_id', 'user_id', 'branch_id', 'department_id', 'team_id', 'reporting_manager_id', 'effective_from', 'effective_to', 'is_primary'];
    protected function casts(): array { return ['effective_from' => 'date', 'effective_to' => 'date', 'is_primary' => 'boolean']; }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function team() { return $this->belongsTo(Team::class); }
    public function reportingManager() { return $this->belongsTo(User::class, 'reporting_manager_id'); }
}
