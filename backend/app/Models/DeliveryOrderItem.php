<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class DeliveryOrderItem extends Model { use HasUuids; protected $fillable=['tenant_id','delivery_order_id','sales_order_item_id','location_id','quantity']; }
