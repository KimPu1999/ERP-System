<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesReturn extends Model { use HasUuids; protected $fillable=['tenant_id','sales_order_id','created_by','number','state','idempotency_key']; public function items(){return $this->hasMany(SalesReturnItem::class);} }
