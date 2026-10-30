<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProjectMember extends Model { use HasUuids; protected $fillable=['tenant_id','project_id','employee_id','role','hourly_rate']; protected function casts():array{return ['hourly_rate'=>'decimal:6'];} public function project(){return $this->belongsTo(Project::class);} public function employee(){return $this->belongsTo(Employee::class);} }
