<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierReportSchedule extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','report_key','filters','frequency','timezone','format','recipients','enabled','next_run_at','last_run_at','created_by']; protected function casts():array{return ['filters'=>'array','recipients'=>'array','enabled'=>'boolean','next_run_at'=>'datetime','last_run_at'=>'datetime'];} }
