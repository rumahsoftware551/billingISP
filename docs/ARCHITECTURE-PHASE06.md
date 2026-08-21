# Jaringanku Phase 06 — Automation Architecture

Phase 06 adds the operational bridge between billing and RADIUS lifecycle.

## Blocking rule

A service becomes billing-blocked when an invoice has `balance_due > 0` and its due date is older than the configured grace period. Example: due 10th + grace 3 days means the customer remains allowed through the 13th and becomes eligible for automatic suspension on the 14th.

## Suspend flow

```text
Invoice overdue beyond grace
        ↓
BillingAutomationService
        ↓
customer_services.status = suspended
        ↓
service_suspensions(active)
        ↓
ServiceStatusHistory
        ↓
RadiusProjectionService removes radcheck/radreply
        ↓
RadiusCoaService disconnects online sessions when enabled
```

Removing the RADIUS projection prevents a disconnected PPPoE client from authenticating again while blocked.

## Reactivation flow

```text
Payment posted
      ↓
Invoice balance recalculated
      ↓
BillingAutomationService evaluates the service
      ↓
No remaining blocking invoices?
      ↓ yes
Only active billing_automation suspension?
      ↓ yes
service.status = active
      ↓
resolve service_suspensions
      ↓
restore radcheck/radreply
      ↓
customer may reconnect
```

Manual/operational suspensions are intentionally never auto-reactivated.

## Idempotency

Repeated automation runs do not create repeated active billing suspensions. A suspended service with no RADIUS projection and no online session is skipped. If a stale projection/session reappears, the next run enforces the suspension again.

## Scheduler

`jaringanku:automation-run` executes every ten minutes via Laravel Scheduler. Payment posting also evaluates the affected service synchronously so successful payment can reactivate immediately.
