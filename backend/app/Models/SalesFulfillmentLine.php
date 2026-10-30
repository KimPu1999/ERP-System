<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesFulfillmentLine extends Model { use HasUuids; protected $fillable=['tenant_id','fulfillment_id','sales_order_item_id','quantity','picked_quantity','packed_quantity','dispatched_quantity','state','exception_note']; protected function casts():array{return ['quantity'=>'decimal:6','picked_quantity'=>'decimal:6','packed_quantity'=>'decimal:6','dispatched_quantity'=>'decimal:6'];} public function fulfillment(){return $this->belongsTo(SalesFulfillment::class,'fulfillment_id');} public function orderItem(){return $this->belongsTo(SalesOrderItem::class,'sales_order_item_id');} }
