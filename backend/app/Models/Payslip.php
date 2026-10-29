<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class Payslip extends Model { use HasUuids; protected $fillable=['tenant_id','payroll_item_id','number','access_token_hash','issued_at']; protected function casts():array{return ['issued_at'=>'datetime'];} public function payrollItem(){return $this->belongsTo(PayrollItem::class,'payroll_item_id');} }
