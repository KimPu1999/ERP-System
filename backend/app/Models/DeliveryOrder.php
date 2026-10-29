<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class DeliveryOrder extends Model { use HasUuids; protected $fillable=['tenant_id','sales_order_id','created_by','number','state','idempotency_key','posted_at']; protected function casts():array{return ['posted_at'=>'datetime'];} public function items(){return $this->hasMany(DeliveryOrderItem::class);} }
