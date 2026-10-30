<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesQuotationVersion extends Model { use HasUuids; protected $table='sales_quotation_versions'; protected $fillable=['tenant_id','quotation_id','version_number','created_by','revision_reason','snapshot']; protected function casts():array{return ['snapshot'=>'array','version_number'=>'integer'];} }
