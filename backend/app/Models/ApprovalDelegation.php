<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ApprovalDelegation extends Model { use HasUuids; protected $fillable=['tenant_id','delegator_id','delegate_id','scenario','starts_at','ends_at','is_active']; protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean'];} }
