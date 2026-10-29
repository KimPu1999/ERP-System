<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ErpNotification extends Model { use HasUuids; protected $table='erp_notifications'; protected $fillable=['tenant_id','user_id','type','title','body','delivery_state','read_at','delivered_at','last_error']; protected function casts():array{return ['read_at'=>'datetime','delivered_at'=>'datetime'];} }
