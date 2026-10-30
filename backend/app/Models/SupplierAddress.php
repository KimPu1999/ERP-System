<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierAddress extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','address_type','recipient','country','region','city','township','postal_code','street_address','is_default','status']; protected function casts():array{return ['is_default'=>'boolean'];} public function supplier(){return $this->belongsTo(Supplier::class);} }
