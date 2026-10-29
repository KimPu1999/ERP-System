<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesInvoice extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','sales_order_id','delivery_order_id','number','state','subtotal','discount_total','tax_total','total','paid_total']; public function items(){return $this->hasMany(SalesInvoiceItem::class,'invoice_id');} public function customer(){return $this->belongsTo(Customer::class);} }
