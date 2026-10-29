<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesOrderItem extends Model { use HasUuids; protected $fillable=['tenant_id','sales_order_id','variant_id','preferred_location_id','ordered_quantity','reserved_quantity','delivered_quantity','returned_quantity','invoiced_quantity','unit_price','discount_rate','tax_rate','line_subtotal','discount_amount','tax_amount','line_total']; }
