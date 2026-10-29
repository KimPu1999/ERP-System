<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesInvoice extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','branch_id','currency_id','exchange_rate','base_total','customer_id','sales_order_id','delivery_order_id','number','state','subtotal','discount_total','tax_total','total','paid_total']; protected function casts(): array { return ['exchange_rate'=>'decimal:12','base_total'=>'decimal:6']; } public function items(){return $this->hasMany(SalesInvoiceItem::class,'invoice_id');} public function customer(){return $this->belongsTo(Customer::class);} }
