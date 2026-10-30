<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierRiskAssessment extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','supplier_id','number','assessment_period','category','description','likelihood','impact','suggested_level','final_level','existing_controls','evidence','risk_owner_id','mitigation_plan','review_due_date','status','override_reason','reviewer_id','approved_at','created_by']; protected function casts():array{return ['evidence'=>'array','review_due_date'=>'date','approved_at'=>'datetime'];} public function supplier(){return $this->belongsTo(Supplier::class);} }
