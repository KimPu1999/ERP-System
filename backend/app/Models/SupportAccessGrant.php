<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupportAccessGrant extends Model { use HasUuids; protected $fillable=['tenant_id','support_user_id','approved_by','reason','starts_at','expires_at','state']; protected function casts():array{return ['starts_at'=>'datetime','expires_at'=>'datetime'];} }
