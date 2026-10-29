<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class LeavePolicy extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','code','annual_entitlement','requires_approval','is_active']; protected function casts():array{return ['annual_entitlement'=>'decimal:4','requires_approval'=>'boolean','is_active'=>'boolean'];} }
