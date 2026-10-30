<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPaymentHold extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','supplier_id','bank_account_id','hold_type','reason','status','created_by','released_by','effective_at','released_at','release_reason']; protected function casts():array{return ['effective_at'=>'datetime','released_at'=>'datetime'];} public function supplier(){return $this->belongsTo(Supplier::class);} public function bankAccount(){return $this->belongsTo(SupplierBankAccount::class,'bank_account_id');} }
