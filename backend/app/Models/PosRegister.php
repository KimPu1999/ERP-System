<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PosRegister extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','branch_id','name','code','status','payment_methods']; protected function casts():array{return ['payment_methods'=>'array'];} public function shifts(){return $this->hasMany(PosShift::class,'register_id');} }
