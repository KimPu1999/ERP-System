<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierQuotationItem extends Model { use HasUuids; protected $table = 'supplier_quotation_items'; protected $fillable = ['tenant_id', 'quotation_id', 'variant_id', 'quantity', 'unit_price']; protected function casts(): array { return ['quantity' => 'decimal:6', 'unit_price' => 'decimal:6']; } }
