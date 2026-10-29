<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class WarehouseLocation extends Model { use HasUuids; protected $table = 'warehouse_locations'; protected $fillable = ['tenant_id', 'warehouse_id', 'parent_id', 'name', 'code', 'is_active']; protected function casts(): array { return ['is_active' => 'boolean']; } public function warehouse() { return $this->belongsTo(Warehouse::class); } }
