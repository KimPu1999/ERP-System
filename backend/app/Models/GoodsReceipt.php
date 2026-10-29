<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class GoodsReceipt extends Model { use HasUuids; protected $table = 'goods_receipts'; protected $fillable = ['tenant_id', 'purchase_order_id', 'received_by', 'number', 'state', 'idempotency_key', 'posted_at']; protected function casts(): array { return ['posted_at' => 'datetime']; } public function items() { return $this->hasMany(GoodsReceiptItem::class); } }
