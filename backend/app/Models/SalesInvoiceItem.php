<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesInvoiceItem extends Model { use HasUuids; protected $fillable=['tenant_id','invoice_id','sales_order_item_id','variant_id','quantity','unit_price','discount_amount','tax_amount','line_total']; protected function casts(): array { return ['quantity'=>'decimal:6','unit_price'=>'decimal:6','discount_amount'=>'decimal:6','tax_amount'=>'decimal:6','line_total'=>'decimal:6']; } public function variant(){return $this->belongsTo(ProductVariant::class);} public function salesOrderItem(){return $this->belongsTo(SalesOrderItem::class,'sales_order_item_id');} }
