<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ApprovalDefinition extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','scenario','name','allow_self_approval','is_active']; protected function casts():array{return ['allow_self_approval'=>'boolean','is_active'=>'boolean'];} public function steps(){return $this->hasMany(ApprovalStep::class,'definition_id')->orderBy('level');} }
