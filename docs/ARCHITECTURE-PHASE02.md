# Phase 02 Architecture

```text
Browser
  |
Nginx :8080
  |
Laravel / Inertia
  |             \
PostgreSQL       RouterOS REST HTTPS
  |                    |
  |                  MikroTik
  |
FreeRADIUS :1812/:1813
  ^
  |
MikroTik RADIUS Client
```

## Data ownership

- `routers`: encrypted REST credentials and router status.
- `network_nas`: encrypted RADIUS client secret, tenant scoped.
- `nas`: FreeRADIUS projection; contains the clear shared secret required by the daemon.
- `internet_plans`: commercial/network package definition.
- `ip_pools`: address allocation metadata.
- `radcheck/radreply`: credential/attribute projection.
- `radacct`: accounting sessions.
- `radpostauth`: authentication results.

Phase 03 introduces customers/services. Phase 04 makes PPPoE/RADIUS projection lifecycle automatic.
