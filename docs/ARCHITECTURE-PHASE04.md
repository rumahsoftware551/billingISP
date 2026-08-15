# Phase 04 Architecture — PPPoE Accounting & Session Control

## Data flow

```text
Customer Service (Jaringanku source of truth)
        |
        +--> radcheck / radreply
        |       +-- Cleartext-Password
        |       +-- Mikrotik-Rate-Limit
        |       +-- Acct-Interim-Interval
        |       +-- Class
        |
        v
   FreeRADIUS 3.2.x
        |
        v
   MikroTik PPPoE NAS
        |
        +--> Accounting-Start --------+
        +--> Interim-Update -----------+--> radacct
        +--> Accounting-Stop ---------+
                                         |
                                         v
                                 Online Sessions UI
                                         |
                            +------------+------------+
                            |                         |
                     CoA-Request              Disconnect-Request
                            |                         |
                            +----------> MikroTik <---+
```

## Source of truth

Business state remains in `customer_services`, `internet_plans`, `network_nas`, etc. The RADIUS tables are operational projections / accounting records.

## Session ownership

`radacct` has no `tenant_id` because it follows the stock FreeRADIUS schema. Jaringanku scopes a session to a tenant by joining:

```text
radacct.username -> customer_services.pppoe_username -> customer_services.tenant_id
```

PPPoE usernames remain globally unique to prevent tenant collisions in shared RADIUS projection tables.

## Session state

An online session is a `radacct` row where:

```text
acctstoptime IS NULL
```

A stale session is currently defined in the UI as an online row whose latest `acctupdatetime` (or start time) is older than 15 minutes. This is diagnostic only; Phase 04 does not automatically delete or close stale accounting rows.

## CoA vs Disconnect

- CoA: apply attributes that RouterOS supports dynamically (Phase 04 uses `Mikrotik-Rate-Limit`).
- Disconnect: terminate the current session so the subscriber must authenticate again.
- IP address, pool, and route changes require disconnect/re-authentication rather than CoA.

## Security

- NAS shared secrets remain encrypted in `network_nas.secret_encrypted`.
- Plaintext secrets in FreeRADIUS `nas` are an operational projection required by the daemon.
- RADIUS action audit logs never store the NAS shared secret.
- `radclient` is invoked without a shell; payload fields are not interpolated into a shell command.
