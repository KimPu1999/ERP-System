<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ChartAccount extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','code','name','type','normal_balance','is_control','is_active']; protected function casts():array{return ['is_control'=>'boolean','is_active'=>'boolean'];} }
