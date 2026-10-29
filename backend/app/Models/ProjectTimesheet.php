<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ProjectTimesheet extends Model { use HasUuids; protected $fillable=['tenant_id','project_id','task_id','employee_id','work_date','hours','state','description']; protected function casts():array{return ['work_date'=>'date','hours'=>'decimal:2'];} }
