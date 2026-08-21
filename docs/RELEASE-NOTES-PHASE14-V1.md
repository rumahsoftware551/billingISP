# Release Notes — Phase 14 FULL V1

Phase 14 memperluas inventory dasar Phase 11 menjadi inventory/warehouse portal mandiri.

## Data model
Menambahkan lokasi inventory, akun portal inventory, login event, SKU, supplier, purchase order, balance per lokasi, transaction ledger, transaction lines, dan stock opname. Asset Phase 11 tetap dipertahankan dan diperluas dengan SKU, lokasi, supplier, PO, barcode, condition, acquisition cost, installed/retrieved timestamps.

## Ledger
Semua perubahan stok utama berjalan melalui `InventoryLedgerService` dan transaksi database. Saldo negatif ditolak. Serialized asset mengikuti lokasi fisik dan lifecycle customer.

## Security
Portal di-scope oleh tenant dan session inventory. Role auditor read-only; teknisi hanya dapat memindahkan/install/retrieve stok dari lokasi yang ditugaskan; warehouse manager memiliki catalog/purchase access.

## Acceptance
`jaringanku:phase14-preflight` memeriksa schema/model/route. `jaringanku:phase14-smoke` menggunakan DB rollback untuk menguji receive, transfer, SN/MAC asset, install/retrieve, dan stock opname tanpa meninggalkan data smoke.
