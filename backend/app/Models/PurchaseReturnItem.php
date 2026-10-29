<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PurchaseReturnItem extends Model { use HasUuids; protected $table = 'purchase_return_items'; protected $fillable = ['tenant_id', 'purchase_return_id', 'purchase_order_item_id', 'variant_id', 'location_id', 'quantity']; protected function casts(): array { return ['quantity' => 'decimal:6']; } }
