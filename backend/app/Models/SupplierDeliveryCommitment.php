<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierDeliveryCommitment extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','company_id','purchase_order_id','purchase_order_item_id','proposed_delivery_date','proposed_quantity','delivery_location','shipment_method','supplier_notes','version','status','created_by']; protected function casts():array{return ['proposed_delivery_date'=>'date','proposed_quantity'=>'decimal:6'];} }
