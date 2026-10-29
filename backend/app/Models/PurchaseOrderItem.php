<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderItem extends Model { use HasUuids; protected $table = 'purchase_order_items'; protected $fillable = ['tenant_id', 'purchase_order_id', 'variant_id', 'description', 'ordered_quantity', 'received_quantity', 'returned_quantity', 'unit_price', 'discount_type', 'discount_amount', 'tax_rate', 'tax_amount', 'line_subtotal', 'line_total']; protected function casts(): array { return ['ordered_quantity' => 'decimal:6', 'received_quantity' => 'decimal:6', 'returned_quantity' => 'decimal:6', 'unit_price' => 'decimal:6', 'discount_amount' => 'decimal:6', 'tax_rate' => 'decimal:6', 'tax_amount' => 'decimal:6', 'line_subtotal' => 'decimal:6', 'line_total' => 'decimal:6']; } public function variant() { return $this->belongsTo(ProductVariant::class); } }
