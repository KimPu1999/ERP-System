<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
class StockMovement extends Model { use HasUuids; protected $fillable = ['tenant_id', 'variant_id', 'location_id', 'created_by', 'movement_type', 'quantity', 'unit_cost', 'reference_type', 'reference_id', 'idempotency_key', 'posted_at']; protected function casts(): array { return ['quantity' => 'decimal:6', 'unit_cost' => 'decimal:6', 'posted_at' => 'datetime']; } protected static function booted(): void { static::updating(fn () => throw new LogicException('Stock movements are immutable.')); static::deleting(fn () => throw new LogicException('Stock movements are immutable.')); } }
