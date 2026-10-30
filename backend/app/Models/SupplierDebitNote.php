<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierDebitNote extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','branch_id','supplier_id','purchase_return_id','supplier_bill_id','number','document_date','reason','tax_adjustment','total','currency_id','state','notes','created_by']; protected function casts():array{return ['document_date'=>'date','tax_adjustment'=>'decimal:6','total'=>'decimal:6'];} public function supplier(){return $this->belongsTo(Supplier::class);} public function currency(){return $this->belongsTo(AccountingCurrency::class,'currency_id');} }
