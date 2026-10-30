<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierContact extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','name','job_title','department','email','phone','is_primary','notes','status']; protected function casts():array{return ['is_primary'=>'boolean'];} public function supplier(){return $this->belongsTo(Supplier::class);} }
