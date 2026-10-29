<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class Project extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','customer_id','manager_id','code','name','state','budget_amount','starts_on','ends_on']; protected function casts():array{return ['budget_amount'=>'decimal:6','starts_on'=>'date','ends_on'=>'date'];} public function tasks(){return $this->hasMany(ProjectTask::class);} public function timesheets(){return $this->hasMany(ProjectTimesheet::class);} }
