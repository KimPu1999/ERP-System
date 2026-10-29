<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class StockTransferLine extends Model { use HasUuids; protected $table = 'stock_transfer_lines'; protected $fillable = ['tenant_id', 'transfer_id', 'variant_id', 'quantity']; protected function casts(): array { return ['quantity' => 'decimal:6']; } }
