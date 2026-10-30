<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPurchasingHold extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','supplier_id','type','reason','effective_from','review_due_date','status','applied_by','released_by','released_at','release_reason']; protected function casts():array{return ['effective_from'=>'date','review_due_date'=>'date','released_at'=>'datetime'];} public function supplier(){return $this->belongsTo(Supplier::class);} }
