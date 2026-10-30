<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierBankAccountReview extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_bank_account_id','reviewer_id','decision','reason','reviewed_at']; protected function casts():array{return ['reviewed_at'=>'datetime'];} public function reviewer(){return $this->belongsTo(User::class,'reviewer_id');} }
