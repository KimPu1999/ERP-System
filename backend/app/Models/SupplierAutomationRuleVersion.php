<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierAutomationRuleVersion extends Model { use HasUuids; protected $fillable=['tenant_id','rule_id','version','definition','created_by']; protected function casts():array{return ['definition'=>'array'];} }
