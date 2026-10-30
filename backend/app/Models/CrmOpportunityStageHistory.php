<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class CrmOpportunityStageHistory extends Model { use HasUuids; protected $table='crm_opportunity_stage_history'; protected $fillable=['tenant_id','opportunity_id','from_stage','to_stage','changed_by','reason']; }
