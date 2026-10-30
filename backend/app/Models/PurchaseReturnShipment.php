<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PurchaseReturnShipment extends Model { use HasUuids; protected $fillable=['tenant_id','purchase_return_id','shipment_number','state','shipment_date','carrier','tracking_reference','created_by','dispatched_by','dispatched_at']; protected function casts():array{return ['shipment_date'=>'date','dispatched_at'=>'datetime'];} public function items(){return $this->hasMany(PurchaseReturnShipmentItem::class,'shipment_id');} }
