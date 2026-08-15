# Upgrade Strategy Jaringanku

- Setiap release wajib memiliki backup database sebelum migration.
- Migration harus forward-compatible sebisa mungkin; hindari drop/rename kolom pada langkah pertama.
- Deploy app baru, jalankan migration, lalu restart queue worker.
- Record release dengan `jaringanku:release-record`.
- Jalankan health/readiness, RADIUS auth, accounting, billing, dan portal checks setelah deploy.
- Named volumes tidak boleh dihapus ketika upgrade.
- Untuk major schema change, gunakan expand/migrate/contract pada release terpisah.
