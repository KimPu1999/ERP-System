<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PurchaseReturn extends Model { use HasUuids; protected $table = 'purchase_returns'; protected $fillable = ['tenant_id', 'purchase_order_id', 'created_by', 'number', 'state', 'idempotency_key']; }
