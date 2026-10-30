<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PurchaseReturnShipmentItem extends Model { use HasUuids; protected $table='purchase_return_shipment_lines'; protected $fillable=['tenant_id','shipment_id','purchase_return_item_id','quantity']; protected function casts():array{return ['quantity'=>'decimal:6'];} }
