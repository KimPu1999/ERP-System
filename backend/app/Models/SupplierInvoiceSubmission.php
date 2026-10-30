<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierInvoiceSubmission extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','company_id','purchase_order_id','supplier_invoice_number','invoice_date','currency_id','claimed_total','document_path','notes','status','idempotency_key','created_by']; protected function casts():array{return ['invoice_date'=>'date','claimed_total'=>'decimal:6'];} }
