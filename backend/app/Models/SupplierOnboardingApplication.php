<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierOnboardingApplication extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','supplier_id','submitted_by','assigned_reviewer_id','approval_request_id','number','application_type','status','approval_status','version','snapshot','notes','change_request_reason','submitted_at','reviewed_at']; protected function casts():array{return ['snapshot'=>'array','submitted_at'=>'datetime','reviewed_at'=>'datetime'];} public function supplier(){return $this->belongsTo(Supplier::class);} public function company(){return $this->belongsTo(Company::class);} public function documents(){return $this->hasMany(SupplierDocument::class,'application_id');} public function approvalRequest(){return $this->belongsTo(ApprovalRequest::class);} }
