# Jaringanku Phase 16 — Commercial Readiness

Full source kumulatif dari **Jaringanku Phase 15 FULL V1**. Phase 16 adalah usability/commercial-readiness pass pertama. Targetnya bukan mengklaim parity penuh dengan RLRadius, tetapi memperbaiki blocker penggunaan harian: portal yang sulit dibuka, UX form, branding, custom payment/QRIS, dan user-role-permission management.

## Yang baru

- Admin shell baru: sidebar modern, navigasi Indonesia, pencarian menu, branding tenant.
- **Access Center** `/access` untuk membuka Admin, Customer Portal, Portal Mitra, dan Portal Inventory tanpa menghafal URL tenant.
- URL placeholder `/portal/tenant/login`, `/mitra/tenant/login`, `/inventory/tenant/login` diarahkan ke Access Center, bukan 404.
- Branding tenant: nama aplikasi/perusahaan, warna, logo aplikasi/login, favicon, kontak dukungan, serta asset logo invoice. PDF invoice/receipt Phase 16 sudah memakai nama ISP/footer custom; embedding gambar logo ke PDF masuk tahap template invoice lanjutan.
- Custom payment: QRIS manual (upload gambar), bank, e-wallet, cash, custom, instruksi, limit, visibility.
- Customer Portal dapat upload bukti pembayaran manual; admin review approve/reject sebelum payment diposting.
- User management per tenant: role, status membership, reset password, role custom, permission matrix.
- Permission middleware diperketat pada pelanggan, billing, network, partner, inventory, field ops, automation, dan report.
- Modernisasi Dashboard, daftar pelanggan, halaman login, Portal Mitra, Portal Inventory, Customer Portal shell, serta label form utama agar lebih mudah dipahami staf non-teknis.
- Regression gate tetap mencakup Phase 04–16.

## URL local penting

- Admin: `http://localhost:8080/login`
- Access Center: `http://localhost:8080/access`
- Settings/Branding/Payment/User: `http://localhost:8080/settings`
- Customer Portal: `http://localhost:8080/portal/demo-isp/login`
- Portal Mitra: `http://localhost:8080/mitra/demo-isp/login`
- Portal Inventory: `http://localhost:8080/inventory/demo-isp/login`
- Bukti Pembayaran: `http://localhost:8080/billing/manual-payments`

Demo local tersedia bila `SEED_DEMO_DATA=true`.

## Jalankan

PowerShell:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\local-up.ps1
```

Jangan gunakan `docker compose down -v` jika ingin mempertahankan database/volume Phase sebelumnya.

## Acceptance

```powershell
.\scripts\phase16-smoke.ps1
.\scripts\final-regression.ps1
```

Phase 16 berstatus **v1.2.0-dev / development**. Target commercial release final masih berada pada roadmap berikutnya; paket ini tidak mengklaim feature parity penuh dengan RLRadius.
