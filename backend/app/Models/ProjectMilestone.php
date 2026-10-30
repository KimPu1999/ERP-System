<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProjectMilestone extends Model { use HasUuids; protected $fillable=['tenant_id','project_id','name','due_on','state']; protected function casts():array{return ['due_on'=>'date'];} public function project(){return $this->belongsTo(Project::class);} public function tasks(){return $this->hasMany(ProjectTask::class,'milestone_id');} }
