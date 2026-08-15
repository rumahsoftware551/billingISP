# Phase 05 Architecture — Billing & Payments

## Source of truth

Customer service and Internet Plan are the source for recurring invoice creation. A monthly `billing_key` (`service:{id}:YYYY-MM`) makes generation idempotent.

## Invoice flow

```text
CustomerService(active)
  -> InternetPlan.price
  -> BillingEngine
  -> Invoice
  -> InvoiceItem snapshot
```

## Payment flow

```text
Invoice(balance_due)
  -> Payment(posted)
  -> PaymentAllocation
  -> recalculate paid_amount/balance_due
  -> partial | paid | overdue
```

All money fields use integer Rupiah to avoid floating-point rounding in core billing.

## Safety

- tenant scope on billing domain models
- database transactions for invoice creation and payment posting
- row lock on invoice during payment
- unique invoice number per tenant
- unique billing key per tenant
- payment cannot exceed invoice balance in Phase 05
- overdue refresh is non-destructive; suspension is intentionally deferred to Phase 06
