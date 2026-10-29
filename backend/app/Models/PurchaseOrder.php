<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrder extends Model { use HasUuids; protected $fillable = ['tenant_id', 'supplier_id', 'created_by', 'quotation_id', 'number', 'state', 'expected_at', 'notes']; protected function casts(): array { return ['expected_at' => 'date']; } public function items() { return $this->hasMany(PurchaseOrderItem::class); } public function supplier() { return $this->belongsTo(Supplier::class); } }
