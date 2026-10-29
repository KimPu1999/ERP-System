<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesInvoiceItem extends Model { use HasUuids; protected $fillable=['tenant_id','invoice_id','sales_order_item_id','variant_id','quantity','unit_price','discount_amount','tax_amount','line_total']; }
