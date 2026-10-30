<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierCorrectiveAction extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','supplier_id','evaluation_id','risk_assessment_id','number','issue_description','root_cause','proposed_action','owner_id','due_date','priority','status','review_notes','verified_by','verified_at','created_by']; protected function casts():array{return ['due_date'=>'date','verified_at'=>'datetime'];} public function supplier(){return $this->belongsTo(Supplier::class);} }
