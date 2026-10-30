<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPortalMessage extends Model { use HasUuids; protected $fillable=['tenant_id','conversation_id','sender_id','body','visibility','read_at']; protected function casts():array{return ['read_at'=>'datetime'];} }
