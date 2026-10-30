<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ShipmentTrackingEvent extends Model { use HasUuids; protected $fillable=['tenant_id','delivery_order_id','status','occurred_at','external_reference','notes','recorded_by']; protected function casts():array{return ['occurred_at'=>'datetime'];} public function shipment(){return $this->belongsTo(DeliveryOrder::class,'delivery_order_id');} }
