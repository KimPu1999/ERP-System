<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderItem extends Model { use HasUuids; protected $table = 'purchase_order_items'; protected $fillable = ['tenant_id', 'purchase_order_id', 'variant_id', 'ordered_quantity', 'received_quantity', 'returned_quantity', 'unit_price']; protected function casts(): array { return ['ordered_quantity' => 'decimal:6', 'received_quantity' => 'decimal:6', 'returned_quantity' => 'decimal:6', 'unit_price' => 'decimal:6']; } }
