<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AccountingCurrency extends Model { use HasUuids; protected $fillable=['tenant_id','code','name','decimal_places','is_base']; protected function casts():array{return ['is_base'=>'boolean'];} }
