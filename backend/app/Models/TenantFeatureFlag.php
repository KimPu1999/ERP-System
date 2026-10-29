<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class TenantFeatureFlag extends Model { use HasUuids; protected $fillable=['tenant_id','feature_flag_id','enabled']; protected function casts():array{return ['enabled'=>'boolean'];} }
