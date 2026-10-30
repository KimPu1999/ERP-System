<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesQuotationItem extends Model { use HasUuids; protected $fillable=['tenant_id','quotation_id','variant_id','description','unit','quantity','unit_price','discount_type','discount_rate','discount_amount','tax_code','tax_rate','line_subtotal','tax_amount','line_total']; protected function casts():array{return ['quantity'=>'decimal:6','unit_price'=>'decimal:6','discount_rate'=>'decimal:6','discount_amount'=>'decimal:6','tax_rate'=>'decimal:6','tax_amount'=>'decimal:6','line_subtotal'=>'decimal:6','line_total'=>'decimal:6'];} public function variant(){return $this->belongsTo(ProductVariant::class,'variant_id');} }
