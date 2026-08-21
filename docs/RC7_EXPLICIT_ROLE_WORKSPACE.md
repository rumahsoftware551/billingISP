# RC7 Explicit Role Workspace Shell

RC7 replaces the generic shared staff navigation shell with explicit workspaces.

The visible navigation is now constructed from a dedicated role map and then
intersected with backend permissions. This is intentionally different from RC6,
which started from one universal navigation catalog and only hid routes.

## Workspaces

- Owner: executive/business/operations/expansion/governance.
- Administrator: daily ISP operations; no System or Access Portal shortcut.
- Finance: billing, payment evidence, reports, customer reference.
- Customer Service: customers, tickets/work orders, billing reference.
- NOC: network/RADIUS, sessions, automation, technical tickets.
- Warehouse: inventory and field-work reference.
- Viewer: monitoring-only workspace with a visible READ ONLY banner.
- Technician: future field-oriented workspace.

Backend authorization remains the security source of truth.
