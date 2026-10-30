<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierRefund extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','supplier_id','purchase_return_id','supplier_credit_note_id','bank_account_id','currency_id','reference','refund_date','amount','verification_status','transaction_reference','notes','created_by']; protected function casts():array{return ['refund_date'=>'date','amount'=>'decimal:6'];} public function supplier(){return $this->belongsTo(Supplier::class);} public function currency(){return $this->belongsTo(AccountingCurrency::class,'currency_id');} }
