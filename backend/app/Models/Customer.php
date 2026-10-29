<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model { use HasUuids; protected $fillable=['tenant_id','name','code','email','phone','billing_address','shipping_address','tax_identifier','status']; public function contacts(){return $this->hasMany(CustomerContact::class);} public function invoices(){return $this->hasMany(SalesInvoice::class);} public function orders(){return $this->hasMany(SalesOrder::class);} }
