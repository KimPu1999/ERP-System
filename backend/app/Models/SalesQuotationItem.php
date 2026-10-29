<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesQuotationItem extends Model { use HasUuids; protected $fillable=['tenant_id','quotation_id','variant_id','quantity','unit_price','discount_rate','tax_rate','line_subtotal','discount_amount','tax_amount','line_total']; }
