<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SubscriptionPlan extends Model { use HasUuids; protected $fillable=['code','name','description','currency_code','monthly_price','annual_price','is_active']; protected function casts():array{return ['monthly_price'=>'decimal:6','annual_price'=>'decimal:6','is_active'=>'boolean'];} public function features(){return $this->hasMany(PlanFeature::class,'plan_id');} }
