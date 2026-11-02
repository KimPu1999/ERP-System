<?php

namespace App\Jobs;

use App\Jobs\Concerns\HasTenantContext;
use App\Mail\SupplierAutomationNotificationMail;
use App\Models\{ErpNotification, SupplierNotificationDelivery};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

final class DeliverSupplierNotification implements ShouldQueue
{
    use Dispatchable, Queueable, HasTenantContext;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(string $tenantId, public readonly string $notificationId, public readonly string $channel)
    {
        $this->tenantId = $tenantId;
    }

    public function backoff(): array { return [60, 300, 900]; }

    public function handle(): void
    {
        $delivery = SupplierNotificationDelivery::where('tenant_id', $this->tenantId)->where('notification_id', $this->notificationId)->where('channel', $this->channel)->lockForUpdate()->firstOrFail();
        if ($delivery->state === 'succeeded') return;
        $notification = ErpNotification::where('tenant_id', $this->tenantId)->with('user')->findOrFail($this->notificationId);
            $delivery->update(['state' => 'attempted', 'attempts' => ((int) $delivery->attempts) + 1, 'attempted_at' => now(), 'last_error' => null]);
        try {
            if ($this->channel === 'email') {
                if (! $notification->user?->email) throw new \RuntimeException('Recipient has no approved email address.');
                Mail::to($notification->user->email)->send(new SupplierAutomationNotificationMail($notification));
                $state = in_array(config('mail.default'), ['log', 'array'], true) ? 'accepted_by_configured_mailer' : 'accepted_by_mailer';
                $delivery->update(['accepted_at' => now()]);
            } else {
                $notification->update(['delivery_state' => 'delivered', 'delivered_at' => now(), 'last_error' => null]);
                $state = 'succeeded';
                $delivery->update(['confirmed_at' => now()]);
            }
            $delivery->update(['state' => $state, 'succeeded_at' => now(), 'last_error' => null]);
            if ($this->channel === 'email') $notification->update(['delivery_state' => $state, 'delivered_at' => now()]);
        } catch (\Throwable $exception) {
            $message = substr($exception->getMessage(), 0, 1000);
            $delivery->update(['state' => 'failed', 'last_error' => $message]);
            $notification->update(['delivery_state' => 'failed', 'last_error' => $message]);
            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $message = substr($exception->getMessage(), 0, 1000);
        SupplierNotificationDelivery::where('tenant_id', $this->tenantId)->where('notification_id', $this->notificationId)->where('channel', $this->channel)->update(['state' => 'failed', 'last_error' => $message]);
        ErpNotification::where('tenant_id', $this->tenantId)->whereKey($this->notificationId)->update(['delivery_state' => 'failed', 'last_error' => $message]);
    }
}
