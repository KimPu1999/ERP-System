<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class WorkSchedule extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','weekly_rules','hours_per_day','is_active']; protected function casts():array{return ['weekly_rules'=>'array','hours_per_day'=>'decimal:2','is_active'=>'boolean'];} }
