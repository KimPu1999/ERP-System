<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class Lead extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','owner_id','name','email','company','state','estimated_value']; protected function casts():array{return ['estimated_value'=>'decimal:6'];} }
