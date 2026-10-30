<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesQuotationApproval extends Model { use HasUuids; protected $table='sales_quotation_approvals'; protected $fillable=['tenant_id','quotation_id','approver_id','state','remarks','decided_at']; protected function casts():array{return ['decided_at'=>'datetime'];} }
