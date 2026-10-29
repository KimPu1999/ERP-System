<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class InventorySetting extends Model { use HasUuids; protected $table = 'inventory_settings'; protected $fillable = ['tenant_id', 'allow_negative_stock']; protected function casts(): array { return ['allow_negative_stock' => 'boolean']; } }
