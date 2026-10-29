<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalaryStructure extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','code','currency_code','rounding_mode','decimal_places']; }
