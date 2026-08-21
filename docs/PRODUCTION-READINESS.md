# Jaringanku Phase 08 — Production Readiness

Phase 08 keeps all Phase 01–07 functionality and adds the operational foundation required before external payment/WhatsApp integrations.

## Added

- `/system` operations console
- liveness `/health/live` and token-protected readiness `/health/ready`
- DB, Redis, writable-storage, queue heartbeat, scheduler heartbeat and failed-job checks
- login rate limiting stored in Redis
- security event log for login, failed login, rate-limit and logout
- request IDs and security response headers
- Laravel 13 cache hardening: `serializable_classes=false`
- encrypted webhook secrets using Laravel encrypted casts
- signed webhook delivery using HMAC-SHA256
- webhook queue/retry delivery log
- outbound webhook SSRF policy: production HTTPS and public-network targets by default
- notification template + outbox foundation (`log` and `email` channels)
- tenant audit log service with request ID and source
- production Compose file with Docker secrets
- daily PostgreSQL custom-format backup service + retention
- manual backup and guarded restore scripts

## Webhook signature

Webhook requests include:

```text
X-Jaringanku-Event
X-Jaringanku-Event-Id
X-Jaringanku-Timestamp
X-Jaringanku-Signature: sha256=<hex>
```

Signing input:

```text
<timestamp>.<raw-json-body>
```

Expected signature:

```text
sha256=HMAC_SHA256(endpoint_secret, signing_input)
```

Compare signatures with a constant-time comparison such as `hash_equals`.

## Webhook target policy

Production defaults:

```env
WEBHOOK_ALLOW_PRIVATE_NETWORKS=false
WEBHOOK_ALLOW_INSECURE_HTTP=false
```

This blocks private/reserved IPv4 and IPv6 targets and requires HTTPS. Hostname deliveries are pinned to the addresses that passed validation, and HTTP redirects are disabled. Only enable private targets when you intentionally operate trusted internal integrations.

## MikroTik outbound target policy

RouterOS REST credentials can be used to reach privileged network resources. In production, configure the exact private/public ranges that contain your routers before adding them in the application:

```env
MIKROTIK_ALLOWED_CIDRS=192.168.88.0/24,10.20.0.0/16
MIKROTIK_REQUIRE_TLS=true
```

The application resolves each router hostname and rejects every resolved address that is outside this allowlist. Hostname connections are pinned to the validated addresses for the request and redirects are disabled, reducing DNS-rebinding and redirect-based SSRF risk. Do not use `0.0.0.0/0` or `::/0`.

## Health endpoints

Application liveness is available at:

```text
GET /health/live
```

Readiness checks core dependencies. If `HEALTH_TOKEN` is configured, send either:

```text
Authorization: Bearer <token>
```

or:

```text
X-Health-Token: <token>
```

The Compose Nginx container uses Laravel's built-in `/up` endpoint for its container healthcheck. The richer dependency view is available only after authentication at `/system`.

## Backup model

The production backup service creates a paired PostgreSQL `pg_dump -Fc` archive and compressed `storage/app` archive for the same UTC timestamp. It writes per-file SHA-256 sidecars plus a combined manifest. Retention is controlled with:

```env
BACKUP_RETENTION_DAYS=14
BACKUP_INTERVAL_SECONDS=86400
```

A backup is not a recovery plan until the paired database-and-storage restore has been tested. Use a staging/local environment for routine restore drills. `prod-restore.sh` intentionally refuses a database-only restore unless a matching `.storage.tar.gz` archive is present.
