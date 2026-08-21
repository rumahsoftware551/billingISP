# RC5 Role-Aware UI & RBAC

## Goal

Make the Jaringanku tenant workspace adapt to each user's job instead of rendering the same general dashboard for everyone.

## Personas

- Owner: executive business + operational health.
- Administrator: tenant operations.
- Finance / Billing: invoice, payment, aging, collection and finance reports.
- Customer Service: customers, service status, tickets and basic billing visibility.
- NOC / Network: router, RADIUS, sessions, isolation and network operations.
- Warehouse / Inventory: stock, assets, serials and technician material flow.
- Read Only: view-only workspace.
- Technician / Field Ops: supported in UI profile for future/default role activation.

## Security model

Role profile controls presentation only. Permission remains the authorization source of truth.
The sidebar continues to filter by permission and privileged system navigation is additionally limited to owner/admin.
Backend permission matrix is repaired using `jaringanku:rbac-repair`.

## Acceptance

- TypeScript production build passes.
- Source regression tests pass.
- RBAC dry-run, apply and verify pass.
- Finance has no network/inventory mutation permissions.
- NOC has no billing mutation permissions.
- Warehouse has no billing/network mutation permissions.
- Viewer receives only `.view` permissions.
- Owner/Admin retain the full tenant permission catalog.
- Production `/version` is `1.3.0-rc5`.
- `main` is not modified.
