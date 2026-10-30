<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class CustomerContact extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','name','email','phone','role','department','preferred_communication','is_primary','status']; protected function casts():array{return ['is_primary'=>'boolean'];} public function customer(){return $this->belongsTo(Customer::class);} }
