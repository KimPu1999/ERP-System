<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierComplianceReview extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','supplier_id','status','required_count','verified_count','missing_count','rejected_count','expired_count','next_review_date','requirement_snapshot','reviewed_by','reviewed_at']; protected function casts():array{return ['next_review_date'=>'date','requirement_snapshot'=>'array','reviewed_at'=>'datetime'];} public function supplier(){return $this->belongsTo(Supplier::class);} }
