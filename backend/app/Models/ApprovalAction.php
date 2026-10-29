<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ApprovalAction extends Model { use HasUuids; protected $fillable=['tenant_id','approval_request_id','approver_id','level','decision','comment','previous_status','new_status','policy_version','request_correlation_id','acted_at']; protected function casts():array{return ['acted_at'=>'datetime'];} public function approver(){return $this->belongsTo(User::class,'approver_id');} }
