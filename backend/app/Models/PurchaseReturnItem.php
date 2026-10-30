<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PurchaseReturnItem extends Model { use HasUuids; protected $table = 'purchase_return_items'; protected $fillable = ['tenant_id','purchase_return_id','purchase_order_item_id','goods_receipt_item_id','variant_id','location_id','quantity','unit_cost','unit','batch_number','serial_number','reason','dispatched_quantity']; protected function casts(): array { return ['quantity'=>'decimal:6','unit_cost'=>'decimal:6','dispatched_quantity'=>'decimal:6']; } public function variant(){return $this->belongsTo(ProductVariant::class);} public function receiptItem(){return $this->belongsTo(GoodsReceiptItem::class,'goods_receipt_item_id');} }
