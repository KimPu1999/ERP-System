<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierBill extends Model { use HasUuids; protected $table = 'supplier_bills'; protected $fillable = ['tenant_id', 'supplier_id', 'purchase_order_id', 'number', 'supplier_reference', 'state', 'subtotal', 'tax_total', 'total']; protected function casts(): array { return ['subtotal' => 'decimal:6', 'tax_total' => 'decimal:6', 'total' => 'decimal:6']; } }
