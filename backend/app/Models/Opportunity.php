<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class Opportunity extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','owner_id','name','state','estimated_value','expected_close_at']; protected function casts():array{return ['estimated_value'=>'decimal:6','expected_close_at'=>'date'];} }
