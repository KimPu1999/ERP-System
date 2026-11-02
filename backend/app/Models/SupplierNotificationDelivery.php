<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupplierNotificationDelivery extends Model
{
    use HasUuids;

    protected $table = 'supplier_notification_deliveries';
    protected $fillable = ['tenant_id', 'notification_id', 'channel', 'state', 'attempts', 'last_error', 'queued_at', 'attempted_at', 'accepted_at', 'confirmed_at', 'succeeded_at'];
    protected function casts(): array { return ['queued_at' => 'datetime', 'attempted_at' => 'datetime', 'accepted_at' => 'datetime', 'confirmed_at' => 'datetime', 'succeeded_at' => 'datetime']; }
    public function notification() { return $this->belongsTo(ErpNotification::class, 'notification_id'); }
}
