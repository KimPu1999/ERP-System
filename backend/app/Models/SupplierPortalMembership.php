<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPortalMembership extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','company_id','user_id','role','status','last_login_at','created_by']; protected function casts():array{return ['last_login_at'=>'datetime'];} public function supplier(){return $this->belongsTo(Supplier::class);} public function company(){return $this->belongsTo(Company::class);} public function user(){return $this->belongsTo(User::class);} }
