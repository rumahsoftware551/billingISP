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

This blocks normal private/reserved IPv4 targets and requires HTTPS. Only enable private targets when you intentionally operate trusted internal integrations.

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

The production backup service uses PostgreSQL `pg_dump -Fc` custom archive format and saves SHA-256 files alongside dumps. Retention is controlled with:

```env
BACKUP_RETENTION_DAYS=14
BACKUP_INTERVAL_SECONDS=86400
```

A backup is not a recovery plan until restore has been tested. Use a staging/local environment for routine restore drills.
