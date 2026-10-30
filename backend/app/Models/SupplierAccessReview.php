<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierAccessReview extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','membership_id','supplier_id','reviewer_id','status','due_date','decision_notes','reviewed_at']; protected function casts():array{return ['due_date'=>'date','reviewed_at'=>'datetime'];} public function membership(){return $this->belongsTo(SupplierPortalMembership::class,'membership_id');} public function supplier(){return $this->belongsTo(Supplier::class);} }
