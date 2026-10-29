<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ApprovalStep extends Model { use HasUuids; protected $fillable=['tenant_id','definition_id','level','permission','department_id','minimum_amount','maximum_amount']; protected function casts():array{return ['minimum_amount'=>'decimal:6','maximum_amount'=>'decimal:6'];} }
