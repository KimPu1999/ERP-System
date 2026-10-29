<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PosReceiptItem extends Model { use HasUuids; protected $fillable=['tenant_id','receipt_id','variant_id','quantity','unit_price','tax_rate','tax_amount','line_total']; protected function casts():array{return ['quantity'=>'decimal:6','unit_price'=>'decimal:6','tax_rate'=>'decimal:6','tax_amount'=>'decimal:6','line_total'=>'decimal:6'];} }
