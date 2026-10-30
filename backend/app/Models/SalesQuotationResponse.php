<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesQuotationResponse extends Model { use HasUuids; protected $table='sales_quotation_responses'; protected $fillable=['tenant_id','quotation_id','version_id','response','source','evidence','responded_at','responded_by']; protected function casts():array{return ['responded_at'=>'datetime'];} }
