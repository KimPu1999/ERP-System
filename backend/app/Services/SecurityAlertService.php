<?php

namespace App\Services;

use App\Models\{MonitoringAlert, SecurityEvent};

final class SecurityAlertService
{
    public function evaluate(SecurityEvent $event): void
    {
        if ($event->category !== 'authentication' || $event->result !== 'failure' || ! in_array($event->event, ['auth.login_failed', 'auth.login_blocked', 'auth.mfa_failed', 'auth.step_up_failed'], true)) return;
        $since = now()->subMinutes(15);
        $ipCount = $event->ip_address ? SecurityEvent::where('ip_address', $event->ip_address)->where('result', 'failure')->where('created_at', '>=', $since)->count() : 0;
        $userCount = $event->user_id ? SecurityEvent::where('user_id', $event->user_id)->where('result', 'failure')->where('created_at', '>=', $since)->count() : 0;
        if (max($ipCount, $userCount) < 5) return;
        $scope = $event->user_id ? 'user:'.$event->user_id : 'ip:'.($event->ip_address ?: 'unknown');
        $fingerprint = hash('sha256', 'authentication-failure-burst|'.$scope);
        $alert = MonitoringAlert::firstOrNew(['fingerprint' => $fingerprint]);
        $alert->fill(['service' => 'authentication', 'severity' => max($ipCount, $userCount) >= 15 ? 'critical' : 'warning', 'title' => 'Repeated authentication failures', 'description' => 'Repeated authentication failures were observed in a fifteen-minute window. This is an investigation signal, not proof of compromise.', 'status' => 'open', 'first_observed_at' => $alert->first_observed_at ?? now(), 'last_observed_at' => now(), 'metadata' => ['event_count_15m' => max($ipCount, $userCount), 'scope_type' => $event->user_id ? 'account' : 'ip']]);
        $alert->save();
    }
}
