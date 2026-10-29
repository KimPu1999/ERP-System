<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ReorderRule extends Model { use HasUuids; protected $fillable = ['tenant_id', 'variant_id', 'location_id', 'reorder_point', 'reorder_quantity']; protected function casts(): array { return ['reorder_point' => 'decimal:6', 'reorder_quantity' => 'decimal:6']; } public function variant() { return $this->belongsTo(ProductVariant::class); } public function location() { return $this->belongsTo(WarehouseLocation::class, 'location_id'); } }
