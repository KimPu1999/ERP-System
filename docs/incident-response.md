# Incident response and operations

## Detection

Alert on repeated 5xx responses, failed health/readiness checks, queue growth, failed jobs, authentication spikes, database connection exhaustion, backup age, restore failures, and unusual authorization-denial volume. Application logs must be centralized with request IDs and secrets/passwords redacted at the collector.

## First response

1. Declare an incident and assign an incident commander.
2. Preserve audit/security logs and identify affected tenant, company, branch, and time window.
3. If isolation is uncertain, stop writes or remove the affected deployment from traffic.
4. Rotate exposed credentials and revoke sessions/tokens as appropriate.
5. Use the last verified backup only after scope and recovery point are agreed.
6. Validate health, tenant isolation, accounting balance, and inventory invariants before reopening writes.

## Supervision

Run separate supervised processes for `php artisan queue:work redis --tries=3 --timeout=120` and `php artisan schedule:work`. Configure restart-on-failure, bounded concurrency, graceful termination, and alerts for failed jobs. Do not run these workers without a process supervisor in staging or production.
