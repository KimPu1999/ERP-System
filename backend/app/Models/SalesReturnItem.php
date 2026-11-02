<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesReturnItem extends Model { use HasUuids; protected $fillable=['tenant_id','sales_return_id','sales_order_item_id','location_id','quantity']; protected function casts():array{return ['quantity'=>'decimal:6'];} public function orderItem(){return $this->belongsTo(SalesOrderItem::class,'sales_order_item_id');} public function location(){return $this->belongsTo(WarehouseLocation::class,'location_id');} }
