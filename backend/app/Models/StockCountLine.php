<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class StockCountLine extends Model { use HasUuids; protected $table = 'stock_count_lines'; protected $fillable = ['tenant_id', 'count_id', 'variant_id', 'counted_quantity']; protected function casts(): array { return ['counted_quantity' => 'decimal:6']; } }
