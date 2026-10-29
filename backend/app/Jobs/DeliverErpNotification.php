<?php
namespace App\Jobs;
use App\Jobs\Concerns\HasTenantContext; use App\Models\ErpNotification; use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable;
final class DeliverErpNotification implements ShouldQueue { use Dispatchable, Queueable, HasTenantContext; public function __construct(string $tenantId,public readonly string $notificationId){$this->tenantId=$tenantId;} public function handle():void{$notification=ErpNotification::where('tenant_id',$this->tenantId)->findOrFail($this->notificationId);$notification->update(['delivery_state'=>'delivered','delivered_at'=>now()]);} }
