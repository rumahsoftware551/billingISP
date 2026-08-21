# Jaringanku Phase 06 FULL V2

## Fix utama

- Suspended PPPoE tidak lagi hanya dihapus dari `radcheck`.
- Projection suspended sekarang menjadi `Auth-Type := Reject`, sehingga NAS menerima `Access-Reject` eksplisit.
- `Cleartext-Password`, `radreply`, dan `radusergroup` dibersihkan ketika isolir.
- Reactivation menghapus marker Reject dan memulihkan `Cleartext-Password` + reply attributes.
- Automation smoke test sekarang wajib melihat `Access-Reject`; timeout tidak dianggap sukses.
- RadiusTestService menangani timeout secara diagnostik tanpa crash exception yang tidak informatif.
- Enforce automation membedakan reject projection yang valid dari credential aktif yang bocor.

## Data

Tidak ada migration baru. Database dan named volume Phase 01-06 tetap dapat digunakan.
