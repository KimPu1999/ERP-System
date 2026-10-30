<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPortalInvitation extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','company_id','email','role','token_hash','status','expires_at','invited_by','accepted_user_id','accepted_at','revoked_at']; protected $hidden=['token_hash']; protected function casts():array{return ['expires_at'=>'datetime','accepted_at'=>'datetime','revoked_at'=>'datetime'];} public function supplier(){return $this->belongsTo(Supplier::class);} public function company(){return $this->belongsTo(Company::class);} }
