<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class EmployeeSalaryAssignment extends Model { use HasUuids; protected $fillable=['tenant_id','employee_id','salary_structure_id','starts_on','ends_on']; protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date'];} public function structure(){return $this->belongsTo(SalaryStructure::class,'salary_structure_id');} }
