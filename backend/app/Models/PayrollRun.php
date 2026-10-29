<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PayrollRun extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','period_starts_on','period_ends_on','state','created_by','approved_by','finalized_by','approved_at','finalized_at','idempotency_key']; protected function casts():array{return ['period_starts_on'=>'date','period_ends_on'=>'date','approved_at'=>'datetime','finalized_at'=>'datetime'];} public function items(){return $this->hasMany(PayrollItem::class);} }
