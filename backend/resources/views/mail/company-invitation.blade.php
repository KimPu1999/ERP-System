<p>You have been invited to join a company in Enterprise ERP Cloud.</p>
<p>Open the invitation link to accept: {{ $frontendUrl }}/accept-invitation?token={{ urlencode($token) }}&invitation={{ $invitation->id }}</p>
<p>This invitation expires at {{ $invitation->expires_at->toIso8601String() }}.</p>
