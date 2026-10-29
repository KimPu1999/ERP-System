<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ScheduledReport extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','created_by','report_key','format','filters','cron_expression','next_run_at','state']; protected function casts():array{return ['filters'=>'array','next_run_at'=>'datetime'];} }
