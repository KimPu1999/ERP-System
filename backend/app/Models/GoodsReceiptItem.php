<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class GoodsReceiptItem extends Model { use HasUuids; protected $table = 'goods_receipt_items'; protected $fillable = ['tenant_id', 'goods_receipt_id', 'purchase_order_item_id', 'location_id', 'quantity']; protected function casts(): array { return ['quantity' => 'decimal:6']; } }
