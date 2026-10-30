<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class DeliveryException extends Model { use HasUuids; protected $fillable=['tenant_id','delivery_order_id','reason','attempted_at','notes','state','recorded_by']; protected function casts():array{return ['attempted_at'=>'datetime'];} }
