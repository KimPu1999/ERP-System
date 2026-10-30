<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierAsnLine extends Model { use HasUuids; protected $table='supplier_asn_lines'; protected $fillable=['tenant_id','asn_id','purchase_order_item_id','expected_shipped_quantity','uom','batch_reference','serial_references']; protected function casts():array{return ['expected_shipped_quantity'=>'decimal:6','serial_references'=>'array'];} }
