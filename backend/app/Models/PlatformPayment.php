<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PlatformPayment extends Model { use HasUuids; protected $fillable=['tenant_id','invoice_id','provider','provider_payment_id','status','amount','currency_code','verified_at','provider_metadata']; protected function casts():array{return ['amount'=>'decimal:6','verified_at'=>'datetime','provider_metadata'=>'array'];} }
