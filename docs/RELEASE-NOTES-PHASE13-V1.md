# Phase 13 — Portal Mitra / Reseller

Phase 13 memulai Jaringanku v1.1. Mitra bukan tenant baru: seluruh data tetap memiliki `tenant_id` ISP dan customer mitra mendapat `partner_id` untuk pembatasan akses lapis kedua.

## Security boundary

- Tenant boundary: `tenant_id`
- Partner boundary: `partner_id`
- Session partner terpisah dari admin/customer portal
- Route partner melakukan ownership checks terhadap customer/invoice
- SaaS subscription tenant tetap diberlakukan
- Customer quota tetap dilock dengan PostgreSQL advisory transaction lock

## Commission

`payment_percent` menyimpan nilai dalam basis points: `1000 = 10%`. Entry memakai `idempotency_key` unik sehingga settlement/payment yang diproses ulang tidak membuat komisi ganda.
