<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class StockReservation extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','branch_id','sales_order_id','sales_order_item_id','location_id','quantity','state','source_reference','reserved_at','released_at']; protected function casts(): array { return ['quantity'=>'decimal:6','reserved_at'=>'datetime','released_at'=>'datetime']; } public function order(){return $this->belongsTo(SalesOrder::class,'sales_order_id');} public function item(){return $this->belongsTo(SalesOrderItem::class,'sales_order_item_id');} public function location(){return $this->belongsTo(WarehouseLocation::class,'location_id');} }
