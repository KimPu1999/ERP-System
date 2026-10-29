<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ProjectTask extends Model { use HasUuids; protected $fillable=['tenant_id','project_id','milestone_id','assignee_id','title','state','estimated_hours']; protected function casts():array{return ['estimated_hours'=>'decimal:2'];} }
