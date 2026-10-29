<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class TenantModule extends Model { use HasUuids; protected $fillable=['tenant_id','module_key','enabled','settings']; protected function casts():array{return ['enabled'=>'boolean','settings'=>'array'];} }
