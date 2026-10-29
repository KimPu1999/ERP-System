<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PlanFeature extends Model { use HasUuids; protected $fillable=['plan_id','feature_key','value','value_type']; }
