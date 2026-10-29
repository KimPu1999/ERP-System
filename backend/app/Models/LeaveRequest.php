<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class LeaveRequest extends Model { use HasUuids; protected $fillable=['tenant_id','employee_id','leave_policy_id','reviewed_by','starts_on','ends_on','days','reason','state','reviewed_at']; protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date','days'=>'decimal:4','reviewed_at'=>'datetime'];} }
