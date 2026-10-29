<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPaymentAllocation extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_payment_id','supplier_bill_id','amount']; protected function casts():array{return ['amount'=>'decimal:6'];} public function payment(){return $this->belongsTo(SupplierPayment::class,'supplier_payment_id');} public function bill(){return $this->belongsTo(SupplierBill::class,'supplier_bill_id');} }
