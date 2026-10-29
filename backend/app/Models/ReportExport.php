<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ReportExport extends Model { use HasUuids; protected $fillable=['tenant_id','requested_by','report_key','format','filters','state','private_path','error','completed_at']; protected function casts():array{return ['filters'=>'array','completed_at'=>'datetime'];} }
