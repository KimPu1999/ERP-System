<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class InventoryBalance extends Model { use HasUuids; protected $fillable = ['tenant_id', 'variant_id', 'location_id', 'quantity', 'reserved_quantity']; protected function casts(): array { return ['quantity' => 'decimal:6', 'reserved_quantity' => 'decimal:6']; } public function variant() { return $this->belongsTo(ProductVariant::class); } public function location() { return $this->belongsTo(WarehouseLocation::class, 'location_id'); } }
