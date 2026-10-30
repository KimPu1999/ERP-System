<p>You have been invited to collaborate with {{ $invitation->company?->name ?? 'the buyer company' }} through the ERP Cloud Supplier Portal.</p>
<p>Use the secure invitation link below. The invitation expires on {{ $invitation->expires_at?->toDateString() }} and can only be accepted by this email address.</p>
<p><a href="{{ rtrim($frontendUrl, '/') }}/supplier-portal/accept-invitation?token={{ urlencode($token) }}">Accept Supplier Portal invitation</a></p>
