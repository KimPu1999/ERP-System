<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProductVariant extends Model { use HasUuids; protected $fillable = ['tenant_id', 'product_id', 'unit_id', 'name', 'sku', 'internal_barcode', 'gs1_identifier', 'cost', 'price', 'attributes', 'is_active']; protected function casts(): array { return ['attributes' => 'array', 'cost' => 'decimal:6', 'price' => 'decimal:6', 'is_active' => 'boolean']; } public function product() { return $this->belongsTo(Product::class); } }
