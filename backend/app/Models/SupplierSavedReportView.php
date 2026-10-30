<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierSavedReportView extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','user_id','name','report_key','filter_schema_version','filters','is_default','is_shared']; protected function casts():array{return ['filters'=>'array','is_default'=>'boolean','is_shared'=>'boolean'];} }
