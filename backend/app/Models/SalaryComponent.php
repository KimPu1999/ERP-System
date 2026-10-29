<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalaryComponent extends Model { use HasUuids; protected $fillable=['tenant_id','salary_structure_id','name','code','kind','calculation_type','value','is_taxable']; protected function casts():array{return ['value'=>'decimal:6','is_taxable'=>'boolean'];} }
