<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PurchaseRequestItem extends Model { use HasUuids; protected $table = 'purchase_request_items'; protected $fillable = ['tenant_id', 'purchase_request_id', 'variant_id', 'quantity']; protected function casts(): array { return ['quantity' => 'decimal:6']; } }
