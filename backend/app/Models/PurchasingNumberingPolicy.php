<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PurchasingNumberingPolicy extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','document_type','prefix','include_year','include_branch_code','sequence_length','sequence_scope','reset_policy','next_sequence']; protected function casts():array{return ['include_year'=>'boolean','include_branch_code'=>'boolean','sequence_length'=>'integer','next_sequence'=>'integer'];} }
