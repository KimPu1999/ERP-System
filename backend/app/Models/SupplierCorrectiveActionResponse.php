<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierCorrectiveActionResponse extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','company_id','corrective_action_id','submitted_by','response','evidence','status']; protected function casts():array{return ['evidence'=>'array'];} }
