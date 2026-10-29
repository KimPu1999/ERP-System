<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierBillMatch extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_bill_id','supplier_bill_line_id','match_type','status','eligible_quantity','billed_quantity','reason','reviewed_by','reviewed_at']; protected function casts():array{return ['eligible_quantity'=>'decimal:6','billed_quantity'=>'decimal:6','reviewed_at'=>'datetime'];} public function line(){return $this->belongsTo(SupplierBillLine::class,'supplier_bill_line_id');} }
