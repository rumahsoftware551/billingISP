# Phase 03 Architecture — Customers & Services

## Domain

```text
Tenant
 └─ Customer
     ├─ Customer Address
     ├─ Customer Contact
     └─ Customer Service
          ├─ Internet Plan
          ├─ Router
          ├─ NAS
          ├─ IP Pool
          └─ Service Status History
```

`customers` adalah identitas pelanggan. Koneksi internet tidak ditaruh langsung pada customer karena satu customer dapat memiliki lebih dari satu service.

## Service lifecycle

```text
draft
  ↓
pending_installation
  ↓
active
  ↓
suspended
  ↓
active

active → terminated
```

Phase 03 hanya menyediakan state dan audit history. Otomasi overdue/suspension/reactivation akan diaktifkan pada Phase 06.

## RADIUS projection

Saat service berstatus `active`:

```text
customer_services
    │ encrypted PPPoE password
    │ plan / static IP / pool
    ▼
RadiusProjectionService
    ├─ radcheck: Cleartext-Password
    └─ radreply:
         ├─ Mikrotik-Rate-Limit
         ├─ Framed-IP-Address (jika static IP)
         └─ Framed-Pool (jika menggunakan IP Pool)
```

Saat service bukan `active`, projection user di `radcheck`, `radreply`, dan `radusergroup` dibersihkan.

## Security

- PPPoE password source-of-truth menggunakan Laravel Crypt pada `pppoe_password_encrypted`.
- `radcheck` memang membutuhkan cleartext password untuk PAP/PPPoE operational authentication; tabel ini adalah projection, bukan master credential.
- PPPoE username dibuat unik secara global pada instalasi agar FreeRADIUS tidak mengalami collision antar tenant.
- Route-bound model customer/network diverifikasi ulang terhadap `CurrentTenant` sebelum mutation.
- Foreign resource seperti Plan/Router/NAS/IP Pool divalidasi harus memiliki `tenant_id` yang sama.

## Numbering

`tenant_sequences` digunakan untuk counter atomik per tenant:

```text
customer → JRG-000001
service  → SRV-000001
```

Counter menggunakan transaction + row lock sehingga lebih aman terhadap create bersamaan.
