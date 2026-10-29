<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesQuotation extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','opportunity_id','created_by','number','state','valid_until','subtotal','discount_total','tax_total','total']; protected function casts():array{return ['valid_until'=>'date'];} public function items(){return $this->hasMany(SalesQuotationItem::class,'quotation_id');} public function customer(){return $this->belongsTo(Customer::class);} public function order(){return $this->hasOne(SalesOrder::class,'quotation_id');} }
