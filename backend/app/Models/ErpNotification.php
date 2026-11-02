<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ErpNotification extends Model
{
    use HasUuids;

    protected $table = 'erp_notifications';
    protected $fillable = ['tenant_id', 'user_id', 'type', 'title', 'body', 'delivery_state', 'read_at', 'delivered_at', 'last_error', 'automation_run_id', 'rule_id', 'supplier_id', 'company_id', 'source_key', 'channel', 'priority'];
    protected function casts(): array { return ['read_at' => 'datetime', 'delivered_at' => 'datetime']; }
    public function deliveries() { return $this->hasMany(SupplierNotificationDelivery::class, 'notification_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
}
