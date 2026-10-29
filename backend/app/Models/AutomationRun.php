<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AutomationRun extends Model { use HasUuids; protected $fillable=['tenant_id','rule_id','idempotency_key','state','attempts','last_error','completed_at']; protected function casts():array{return ['completed_at'=>'datetime'];} }
