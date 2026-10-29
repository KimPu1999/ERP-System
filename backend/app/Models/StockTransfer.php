<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class StockTransfer extends Model { use HasUuids; protected $fillable = ['tenant_id', 'from_location_id', 'to_location_id', 'created_by', 'status', 'idempotency_key', 'reason']; public function lines() { return $this->hasMany(StockTransferLine::class, 'transfer_id'); } }
