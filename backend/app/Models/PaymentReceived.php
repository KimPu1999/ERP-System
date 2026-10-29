<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PaymentReceived extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','received_by','reference','method','state','amount','verified_at','verified_by']; protected function casts():array{return ['verified_at'=>'datetime'];} public function allocations(){return $this->hasMany(PaymentAllocation::class,'payment_id');} }
