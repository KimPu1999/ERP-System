<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierDocumentReview extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_document_id','reviewer_id','decision','notes','reviewed_at']; protected function casts():array{return ['reviewed_at'=>'datetime'];} public function document(){return $this->belongsTo(SupplierDocument::class,'supplier_document_id');} public function reviewer(){return $this->belongsTo(User::class,'reviewer_id');} }
