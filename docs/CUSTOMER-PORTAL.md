# Phase 10 — Customer Portal

## URL design

Each tenant receives its own portal namespace:

```text
/portal/{tenant-slug}/login
```

The tenant slug is resolved before authentication. A portal account can only access records matching both its `tenant_id` and `customer_id`.

## Admin provisioning

Open a customer record in the admin UI. The **Customer Portal** card can:

- activate a portal account,
- set or generate a temporary password,
- reset password,
- disable or reactivate portal access.

Generated passwords are flashed once. They are never stored in plaintext.

## Portal access

Customers can log in with either:

- portal email, or
- customer number such as `JRG-000001`.

Temporary-password accounts are redirected to the profile page until a new password is set.

## Available self-service features

- service and package status,
- invoice/outstanding list,
- invoice detail,
- payment history,
- invoice PDF download,
- payment receipt PDF download,
- payment link / QRIS initiation using the Phase 09 gateway,
- local mock QRIS settlement for development only.

## Security controls

- rate-limited login,
- session regeneration after successful login,
- explicit tenant/customer authorization on portal resources,
- disabled portal accounts lose access immediately,
- login/failure/logout audit events,
- generated password minimum 10 characters,
- private portal pages are network-only and are not cached by the service worker.

## PWA foundation

`manifest.webmanifest`, portal icons and `portal-sw.js` are included. The service worker only caches static public shell assets. Private portal responses are never cached.

## Production

Use HTTPS, secure/encrypted sessions, strong customer passwords, and real payment gateway configuration. Local mock payment is blocked outside the local environment.
