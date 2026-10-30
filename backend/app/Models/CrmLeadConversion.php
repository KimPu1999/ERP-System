<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class CrmLeadConversion extends Model { use HasUuids; protected $table='crm_lead_conversions'; protected $fillable=['tenant_id','lead_id','customer_id','opportunity_id','converted_by','idempotency_key','converted_at']; protected function casts():array{return ['converted_at'=>'datetime'];} }
