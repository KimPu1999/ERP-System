<p>You have been invited to administer Enterprise ERP Cloud.</p>
<p>This invitation expires on {{ $invitation->expires_at?->toDayDateTimeString() }}.</p>
<p><a href="{{ rtrim($frontendUrl, '/') }}/platform/accept-invite?token={{ urlencode($token) }}">Accept platform invitation</a></p>
<p>If you were not expecting this invitation, you can ignore this email.</p>
