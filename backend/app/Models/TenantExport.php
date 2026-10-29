<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class TenantExport extends Model { use HasUuids; protected $fillable=['tenant_id','requested_by','state','private_path','error','completed_at']; protected function casts():array{return ['completed_at'=>'datetime'];} }
