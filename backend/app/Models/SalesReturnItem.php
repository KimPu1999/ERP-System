<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesReturnItem extends Model { use HasUuids; protected $fillable=['tenant_id','sales_return_id','sales_order_item_id','location_id','quantity']; }
