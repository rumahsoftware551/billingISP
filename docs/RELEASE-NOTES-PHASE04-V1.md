# Jaringanku Phase 04 FULL V1

Base: **Phase 03 FULL V3**, which passed the user's local Docker test.

## Added

- RADIUS accounting lifecycle using existing FreeRADIUS `radacct` schema.
- Online PPPoE session dashboard at `/network/sessions`.
- Recent Accounting-Stop history and traffic counters.
- `Acct-Interim-Interval` per Internet Plan (default 300 seconds).
- `Class = jaringanku-service-{id}` projection to correlate accounting packets.
- MikroTik CoA rate-limit action using `Mikrotik-Rate-Limit`.
- RADIUS Disconnect-Request action for active sessions.
- CoA/Disconnect audit table `radius_action_logs`.
- Automatic disconnect when an active customer service is suspended/terminated.
- Automatic CoA when an active service changes Internet Plan.
- Best-effort disconnect of an old username when PPPoE username changes.
- Accounting Start/Interim/Stop smoke test command.
- Synthetic online-session demo command for testing the UI without a real MikroTik.

## Preserved fixes

- PHP 8.5 Docker build without separately compiling OPcache.
- PostgreSQL 18 volume layout.
- Staged local startup and health checks.
- Nginx Docker DNS runtime resolution.
- FreeRADIUS 3.2.10 PostgreSQL `group_attribute` fix.
- Dynamic Docker network allocation (no fixed subnet overlap).
- Dynamic internal RADIUS client IP resolution.
- Protection against duplicate localhost/external RADIUS clients.

## MikroTik CoA convention

RouterOS `/radius incoming` defaults to UDP 1700. Jaringanku standardizes its NAS record to UDP **3799** (RFC 3576/RFC 5176 convention), so configure RouterOS explicitly:

```routeros
/radius incoming set accept=yes port=3799
```

The NAS `coa_port` remains configurable if an ISP intentionally uses another port.

## Upgrade hardening

- Phase 04 does not inherit `RADIUS_CLIENT_NETWORK` from older local packages. Real NAS clients should normally come from **Network > NAS** / the SQL `nas` projection. This avoids duplicate FreeRADIUS client definitions during upgrades.
