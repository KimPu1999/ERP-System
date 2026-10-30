<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPoAcknowledgement extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','company_id','purchase_order_id','user_id','type','notes','po_version','idempotency_key','acknowledged_at']; protected function casts():array{return ['acknowledged_at'=>'datetime'];} public function purchaseOrder(){return $this->belongsTo(PurchaseOrder::class);} }
