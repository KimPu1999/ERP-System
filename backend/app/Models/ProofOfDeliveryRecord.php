<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ProofOfDeliveryRecord extends Model { use HasUuids; protected $fillable=['tenant_id','delivery_order_id','status','delivered_at','received_by','delivery_location','delivery_notes','verification_method','recorded_by']; protected function casts():array{return ['delivered_at'=>'datetime'];} }
