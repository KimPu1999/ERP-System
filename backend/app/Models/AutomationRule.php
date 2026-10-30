<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AutomationRule extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','branch_id','event_key','rule_type','name','conditions','actions','frequency','timezone','reminder_delay_minutes','recipients','channels','created_by','is_active']; protected function casts():array{return ['conditions'=>'array','actions'=>'array','recipients'=>'array','channels'=>'array','is_active'=>'boolean','reminder_delay_minutes'=>'integer'];} }
