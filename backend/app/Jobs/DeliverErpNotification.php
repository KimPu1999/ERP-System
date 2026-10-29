<?php
namespace App\Jobs;
use App\Jobs\Concerns\HasTenantContext; use App\Models\ErpNotification; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Bus\Queueable;
final class DeliverErpNotification implements ShouldQueue { use Queueable,HasTenantContext; public function __construct(string $tenantId,public readonly string $notificationId){$this->tenantId=$tenantId;} public function handle():void{$notification=ErpNotification::where('tenant_id',$this->tenantId)->findOrFail($this->notificationId);$notification->update(['delivery_state'=>'delivered','delivered_at'=>now()]);} }
