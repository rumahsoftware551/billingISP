# Jaringanku Inventory / Warehouse Portal

## Akses
- Admin tenant: `/inventory-management`
- Portal inventory: `/inventory/{tenant-slug}/login`

## Role
- `warehouse_manager`: master SKU/supplier, PO, receive, transfer, stock opname, install/retrieve.
- `warehouse_staff`: receive, transfer, stock opname, install/retrieve.
- `technician`: hanya stok pada lokasi teknisinya, install ke customer, retrieve ke lokasi sendiri, return/transfer keluar dari lokasi sendiri.
- `auditor`: read-only.

## Lokasi
`inventory_locations` mendukung warehouse, technician, transit, dan repair. Stok teknisi diperlakukan sebagai lokasi inventory sehingga perpindahan gudang → teknisi tetap tercatat sebagai ledger movement.

## SKU dan Asset
SKU non-serialized menyimpan balance quantity. SKU serialized menghasilkan `inventory_items` individual dengan asset code, serial number, MAC, barcode, kondisi, biaya perolehan dan lifecycle lokasi/customer.

## Movement
Semua operasi portal menggunakan `InventoryLedgerService` di dalam database transaction:
- Receive
- Transfer / issue technician
- Install ke customer service
- Retrieve dari customer
- Stock opname / adjustment

Saldo negatif ditolak. Transfer membawa average cost ke lokasi tujuan. Serialized item harus dipilih per asset/SN.

## Purchase Order
PO mengikat supplier, destination warehouse dan SKU. Penerimaan PO membentuk stock transaction dan mengupdate received quantity/status PO.

## Customer asset lifecycle
`Warehouse -> Technician -> Customer -> Retrieve -> Warehouse/Repair`.

Asset yang sudah dikelola Phase 14 tidak boleh di-assignment melalui legacy Field Operations inventory endpoint karena akan melewati ledger; aplikasi menolak operasi tersebut dan mengarahkan penggunaan Portal Inventory.

## Audit
`inventory_transactions`, `inventory_transaction_lines`, dan `inventory_movements` menyimpan actor, lokasi asal/tujuan, customer service, asset, quantity, reference dan timestamp.
