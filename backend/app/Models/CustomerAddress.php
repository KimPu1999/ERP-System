<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class CustomerAddress extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','label','address_type','country','region','city','township','postal_code','street_address','is_default_billing','is_default_shipping']; protected function casts():array{return ['is_default_billing'=>'boolean','is_default_shipping'=>'boolean'];} public function customer(){return $this->belongsTo(Customer::class);} }
