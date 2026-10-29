<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesOrder extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','quotation_id','created_by','number','state','subtotal','discount_total','tax_total','total']; public function items(){return $this->hasMany(SalesOrderItem::class);} public function customer(){return $this->belongsTo(Customer::class);} public function quotation(){return $this->belongsTo(SalesQuotation::class);} public function approvals(){return $this->hasMany(SalesApproval::class);} }
