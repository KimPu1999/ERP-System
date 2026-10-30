<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SalesOrderStatusHistory extends Model { use HasUuids; protected $table='sales_order_status_history'; protected $fillable=['tenant_id','sales_order_id','actor_id','field','from_value','to_value','reason']; public function order(){return $this->belongsTo(SalesOrder::class,'sales_order_id');} public function actor(){return $this->belongsTo(User::class,'actor_id');} }
