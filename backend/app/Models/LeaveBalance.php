<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class LeaveBalance extends Model { use HasUuids; protected $fillable=['tenant_id','employee_id','leave_policy_id','year','entitled','used']; protected function casts():array{return ['entitled'=>'decimal:4','used'=>'decimal:4'];} }
