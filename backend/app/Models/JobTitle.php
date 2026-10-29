<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class JobTitle extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','code','description','is_active']; protected function casts():array{return ['is_active'=>'boolean'];} }
