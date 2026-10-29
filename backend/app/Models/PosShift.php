<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PosShift extends Model { use HasUuids; protected $fillable=['tenant_id','register_id','cashier_id','state','opening_cash','closing_cash','expected_cash','opened_at','closed_at','closing_notes']; protected function casts():array{return ['opening_cash'=>'decimal:6','closing_cash'=>'decimal:6','expected_cash'=>'decimal:6','opened_at'=>'datetime','closed_at'=>'datetime'];} public function register(){return $this->belongsTo(PosRegister::class,'register_id');} }
