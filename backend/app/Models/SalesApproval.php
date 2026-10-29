<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesApproval extends Model { use HasUuids; protected $fillable=['tenant_id','sales_order_id','approver_id','level','state','comment','decided_at']; protected function casts():array{return ['decided_at'=>'datetime'];} }
