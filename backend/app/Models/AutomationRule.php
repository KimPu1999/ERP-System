<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AutomationRule extends Model { use HasUuids; protected $fillable=['tenant_id','event_key','name','conditions','actions','is_active']; protected function casts():array{return ['conditions'=>'array','actions'=>'array','is_active'=>'boolean'];} }
