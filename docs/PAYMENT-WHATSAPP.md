# Phase 09 — Payment Gateway, QRIS & WhatsApp

## 1. Local acceptance mode

The local profile defaults to a deterministic mock payment gateway and WhatsApp LOG adapter. This is deliberate: Phase 09 can prove invoice settlement, idempotency, queues, notification logs, and UI flow without making an external transaction.

Run:

```powershell
.\scripts\phase09-smoke.ps1
```

A PASS requires a mock transaction to settle only once, the invoice to become paid, and a queued WhatsApp message to reach `sent` with a synthetic `wamid.mock.*` provider ID.

## 2. Midtrans

Configure in **Integrations → Payment Gateway**:

- provider: `midtrans`
- environment: `sandbox` first
- Merchant ID (optional metadata)
- Client Key
- Server Key
- enabled payment methods
- expiry minutes

Jaringanku stores Client Key and Server Key encrypted through Laravel encrypted model casts.

Notification endpoint:

```text
https://YOUR-DOMAIN/api/payments/midtrans/notification
```

The callback processor:

1. identifies the gateway transaction by `order_id`;
2. validates the Midtrans SHA-512 signature;
3. verifies `gross_amount` equals the stored Jaringanku transaction amount;
4. serializes processing with a Redis lock;
5. posts a Jaringanku Payment at most once;
6. runs the existing billing/reactivation workflow;
7. stores callback audit state in `payment_gateway_events`.

The browser-side status refresh does not turn an invoice into paid. Payment posting is intentionally tied to the verified notification callback (or the local-only mock settlement handler).

## 3. QRIS

For Midtrans Snap, select the payment methods required by your merchant account. The default Phase 09 list includes GoPay, ShopeePay, `other_qris`, and common virtual-account methods. Actual methods shown to a payer still depend on the Midtrans merchant configuration.

## 4. WhatsApp Cloud API

Configure in **Integrations → WhatsApp**:

- mode: `cloud`
- Graph API version
- Phone Number ID
- WhatsApp Business Account ID (metadata)
- Access Token
- App Secret
- Verify Token
- default country code (Indonesia: `62`)
- template language
- approved Meta template-name mappings

Secrets are stored encrypted.

Webhook URL per tenant:

```text
https://YOUR-DOMAIN/api/whatsapp/{tenant-slug}/webhook
```

The GET endpoint handles verification challenge. The POST endpoint verifies `X-Hub-Signature-256` when an App Secret is configured, then records delivery/read/failure status updates for known provider message IDs.

## 5. Notification templates

Jaringanku internal template codes:

```text
billing.invoice_created
billing.overdue
billing.payment_received
```

Map each code to an approved Meta template name for production use. The variables are sent in body-parameter order from Jaringanku's notification payload.

If no Meta template mapping exists, the Cloud adapter falls back to a text message. In production you must still respect Meta's customer-service-window/template rules; for automated billing reminders the recommended setup is to map approved templates instead of relying on free-form text.

## 6. Scheduler

Phase 09 schedules:

```text
jaringanku:payment-expire      every 5 minutes
jaringanku:payment-reminders   daily at 09:00
```

Overdue reminder creation is guarded to at most one WhatsApp reminder per invoice per calendar day.

## 7. Production checklist

Before enabling real payments/messages:

- use HTTPS and a public domain;
- switch Midtrans from Sandbox only after Sandbox acceptance tests pass;
- configure the exact Midtrans notification URL;
- keep the Midtrans Server Key server-side only;
- enable approved Meta WhatsApp templates;
- configure the Meta webhook URL and verification token;
- set an App Secret so webhook signatures are enforced;
- check queue/scheduler health on `/system`;
- take a database backup before changing production integration credentials.
