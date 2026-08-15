# Phase 07 Reporting Model

## Dashboard

The executive dashboard uses the active tenant only. `radacct` has no tenant column, so network/accounting queries always join `radacct.username` to `customer_services.pppoe_username` and filter `customer_services.tenant_id`.

## Financial analytics

- Invoiced: sum of `invoices.total` by `issued_at`.
- Payments: sum of posted `payments.amount` by `paid_at`.
- Outstanding: current sum of `invoices.balance_due > 0`.
- Collection rate: payments in the selected period divided by invoices issued in the selected period. It may exceed 100% when customers pay balances from earlier periods.

## Aging

Current open balance is grouped by invoice due date:

- current / not due
- 1–30 days
- 31–60 days
- 61–90 days
- >90 days

The smoke test reconciles the sum of aging buckets against the current invoice outstanding total.

## Exports

Supported CSV types:

- customers
- services
- invoices
- outstanding
- payments
- sessions

Files are UTF-8 with BOM. Values beginning with `=`, `+`, `-` or `@` are prefixed with an apostrophe before CSV output to reduce spreadsheet formula-injection risk.
