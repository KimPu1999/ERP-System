<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class TaxConfiguration extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','code','name','rate','output_account_id','input_account_id','is_active']; protected function casts():array{return ['rate'=>'decimal:6','is_active'=>'boolean'];} }
