<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierAdvanceShippingNotice extends Model { use HasUuids; protected $table='supplier_advance_shipping_notices'; protected $fillable=['tenant_id','supplier_id','company_id','purchase_order_id','number','planned_dispatch_date','expected_arrival_date','carrier','tracking_reference','package_count','package_details','notes','status','idempotency_key','created_by']; protected function casts():array{return ['planned_dispatch_date'=>'date','expected_arrival_date'=>'date'];} public function lines(){return $this->hasMany(SupplierAsnLine::class,'asn_id');} public function purchaseOrder(){return $this->belongsTo(PurchaseOrder::class);} }
