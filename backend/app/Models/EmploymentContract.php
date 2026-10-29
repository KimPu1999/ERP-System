<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class EmploymentContract extends Model { use HasUuids; protected $fillable=['tenant_id','employee_id','contract_type','starts_on','ends_on','status','base_salary','currency_code','pay_frequency','policy_snapshot']; protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date','base_salary'=>'decimal:6','policy_snapshot'=>'array'];} }
