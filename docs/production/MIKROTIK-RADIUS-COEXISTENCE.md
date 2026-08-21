# Coexistence RADIUS pada satu MikroTik

Dokumen ini dipakai ketika satu MikroTik PPPoE masih melayani pelanggan
produksi melalui **RLradius**, sementara Jaringanku/billing baru sedang
dipilotkan.  Tujuannya adalah tidak mengganggu autentikasi, accounting, atau
isolir pelanggan aktif.

## Prinsip yang tidak boleh dilanggar

- Jangan menambahkan dua entri `/radius` aktif dengan `service=ppp` lalu
  menganggap MikroTik akan memilih server berdasarkan pelanggan.  Urutan entri
  `/radius` adalah signifikan; entri tambahan adalah mekanisme prioritas atau
  failover, bukan pemilah pelanggan lama dan baru.
- Jangan jadikan `Access-Reject` dari RLradius sebagai sinyal untuk mencoba
  billing baru.  Reject adalah hasil autentikasi yang sah, bukan timeout.
- Jangan menyalin shared secret RLradius ke billing baru.  Buat secret baru,
  simpan hanya di secret manager/file mode `0600`, dan jangan masukkan ke git,
  tiket, atau screenshot.
- Jangan memakai alamat WinBox/DDNS sebagai alamat sumber RADIUS.  Allowlist
  `RADIUS_CLIENT_NETWORK` harus memuat alamat paket UDP yang *benar-benar
  diterima* oleh server RADIUS baru.

Referensi perilaku RouterOS: urutan klien RADIUS bersifat signifikan dan
`accounting-backup` hanya menandai server accounting cadangan.  RouterOS juga
memiliki satu listener global `/radius incoming` untuk Disconnect/CoA.
Lihat [dokumentasi RADIUS MikroTik](https://help.mikrotik.com/docs/spaces/ROS/pages/328097/RADIUS).

## Arsitektur pilot yang direkomendasikan

Selama pelanggan RLradius masih aktif, gunakan **satu** endpoint RADIUS yang
dihubungi MikroTik, yaitu RADIUS proxy/AAA gateway.  Gateway tersebut memilih
backend berdasarkan aturan yang eksplisit:

```text
PPPoE client
    -> MikroTik
       -> AAA gateway/proxy tunggal
          -> RLradius (pelanggan lama)
          -> FreeRADIUS Jaringanku (pelanggan pilot/baru)
```

Aturan pemisah harus dapat diuji dan tidak tumpang tindih.  Pilih salah satu:

1. **Prefix username** — contoh pelanggan baru selalu `jrg-<nomor>`;
2. **Realm username** — contoh pelanggan baru `nomor@jaringanku`;
3. **PPPoE service name** — gunakan server PPPoE/pool pilot terpisah dan
   gateway melakukan routing dari `Called-Station-Id`.

Pilihan pertama biasanya paling mudah diaudit; pilihan ketiga cocok bila
pelanggan pilot memang memakai service name PPPoE khusus.  Jangan membangun
aturan “kalau username tidak ditemukan di Jaringanku, coba RLradius” tanpa
prosedur proxy yang diuji: akun suspend di Jaringanku dapat salah lolos ke
backend lama bila namespace username bertabrakan.

> Implementasi FreeRADIUS dalam repo ini saat ini melayani database
> Jaringanku secara langsung dan **belum** menjadi proxy RLradius.  Karena itu
> MikroTik belum boleh dialihkan ke container Jaringanku selama fase
> coexistence sebelum gateway/proxy dan uji integrasinya selesai.

## Data non-rahasia yang harus dikonfirmasi sebelum konfigurasi

Siapkan nilai berikut di runbook privat (bukan di repositori publik):

| Nilai | Kegunaan |
| --- | --- |
| `MIKROTIK_RADIUS_SOURCE_IP` | Alamat sumber UDP dari MikroTik yang terlihat oleh server baru atau VPN. |
| `NEW_AAA_IP` | Alamat private/VPN gateway RADIUS baru yang dapat dirutekan dari MikroTik. |
| `LEGACY_AAA_IP:PORT` | Endpoint RLradius lama untuk upstream gateway, bukan secret-nya. |
| `COA_PORT` | Satu port `/radius incoming` global yang dipakai kedua backend. RouterOS default-nya 1700. |
| Namespace pilot | Prefix, realm, atau service name yang dipilih dan contoh username. |

Gunakan path VPN/routable private bila MikroTik atau VPS berada di balik
CGNAT.  Jangan mengekspos UDP RADIUS ke Internet umum.

## Konfigurasi server Jaringanku untuk fase pilot

Di file privat `.env.production` untuk deployment Jaringanku, gunakan secret
RADIUS baru dan batasi NAS ke alamat sumber yang sudah diverifikasi:

```dotenv
# Port yang dipublish host untuk FreeRADIUS Jaringanku.
RADIUS_AUTH_PORT=1812
RADIUS_ACCT_PORT=1813

# Bukan IP server RADIUS; ini adalah source IP/CIDR paket dari NAS.
RADIUS_CLIENT_NETWORK=<MIKROTIK_RADIUS_SOURCE_IP>/32
```

Tambahkan NAS di menu **Network → NAS / RADIUS Clients** dengan `nasname`
sama dengan `MIKROTIK_RADIUS_SOURCE_IP`, secret baru, serta `coa_port` yang
sama dengan listener RouterOS.  Setelah NAS disinkronkan, restart container
RADIUS sesuai prosedur deployment.  Jangan gunakan `0.0.0.0/0`.

## Template MikroTik — hanya setelah AAA gateway siap

Simpan export sebelum perubahan dan jalankan pada maintenance window.  Nilai
di bawah adalah placeholder, bukan konfigurasi yang siap ditempel.

```routeros
# Jangan menjalankan baris ini apabila RLradius masih endpoint langsung.
# Tambahkan HANYA gateway/proxy tunggal yang telah lulus test.
/radius add \
    service=ppp \
    address=<NEW_AAA_IP> \
    authentication-port=1812 \
    accounting-port=1813 \
    src-address=<MIKROTIK_RADIUS_SOURCE_IP> \
    secret=<SECRET_BARU_TIDAK_DITULIS_DI_CHAT> \
    timeout=1s \
    comment="AAA gateway pilot Jaringanku"

/ppp aaa set use-radius=yes accounting=yes interim-update=5m

# Port ini global pada RouterOS. Samakan dengan field coa_port pada NAS Jaringanku.
/radius incoming set accept=yes port=<COA_PORT>
```

Firewall input pada MikroTik harus mengizinkan UDP `<COA_PORT>` hanya dari
alamat gateway Jaringanku/RLradius yang memang diizinkan.  Firewall VPS harus
mengizinkan UDP 1812/1813 hanya dari `MIKROTIK_RADIUS_SOURCE_IP` atau alamat
gateway, bergantung pada topologi akhir.

## Urutan uji penerimaan

1. Buat satu akun pilot dengan namespace baru dan satu akun lama pembanding.
2. Pastikan login pilot menerima `Access-Accept` dari Jaringanku dan mendapat
   `Mikrotik-Rate-Limit`, `Framed-Pool`, serta `Acct-Interim-Interval` yang
   benar.
3. Pastikan login akun RLradius tetap diputuskan/diterima oleh backend lama
   sesuai kondisi semula, tanpa record pilot baru muncul di billing lama.
4. Verifikasi `Accounting-Start`, interim update, dan `Accounting-Stop` hanya
   masuk ke backend yang tepat. Cocokkan `NAS-IP-Address`, username, dan
   `Acct-Session-Id`.
5. Dari Jaringanku, lakukan satu Disconnect dan satu perubahan paket/CoA pada
   akun pilot. Pastikan port incoming dan firewall berfungsi sebelum menguji
   isolir otomatis.
6. Pantau `/radius monitor` untuk `timeouts`, `bad-replies`, dan `rejects`.
   Rollback bila angka meningkat atau pelanggan lama terdampak.

Promosi dari pilot ke migrasi massal baru boleh dilakukan setelah seluruh uji
di atas lulus dan backup konfigurasi MikroTik serta backup database/storage
aplikasi sudah diverifikasi.
6c0be77e60b3a668eabbd4964eb730b7a1ad368b