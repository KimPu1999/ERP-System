<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class StockAdjustment extends Model { use HasUuids; protected $fillable = ['tenant_id', 'created_by', 'status', 'idempotency_key', 'reason']; }
