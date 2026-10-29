<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class InventoryAlert extends Model { use HasUuids; protected $fillable = ['tenant_id', 'variant_id', 'location_id', 'type', 'resolved_at']; protected function casts(): array { return ['resolved_at' => 'datetime']; } public function variant() { return $this->belongsTo(ProductVariant::class); } public function location() { return $this->belongsTo(WarehouseLocation::class, 'location_id'); } }
