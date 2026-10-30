<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PurchasingNotificationPreference extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','user_id','event_type','channel','frequency','is_enabled','reminder_interval_minutes']; protected function casts():array{return ['is_enabled'=>'boolean','reminder_interval_minutes'=>'integer'];} }
