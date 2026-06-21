# ERPOS

ERP berbasis Laravel untuk distribusi/penerbitan dengan modul:
- Master data: barang, customer, supplier
- Transaksi: faktur penjualan, retur, surat jalan, surat pesanan, transaksi keluar
- Piutang customer + hutang supplier + mutasi
- Report print/PDF/Excel
- Audit log finansial

## Tipe transaksi customer
- Ada field baru: `Tipe Transaksi`
- Opsi:
  - `Produk`
  - `Cetak`
- Default:
  - `Produk`
- Kalau pilih `Cetak`, muncul field tambahan:
  - `Subjenis Cetak`
- Contoh `Subjenis Cetak`:
  - `LKS`
  - `KBR`
  - `Buku Cerita`
- `Subjenis Cetak` dikunci per customer
  - jadi daftar customer A bisa berbeda dengan customer B
  - list dropdown tetap pendek dan tidak tercampur semua customer
- jika subjenis belum ada, bisa tambah langsung saat input transaksi
- Dipakai di:
  - `Surat Pesanan`
  - `Faktur Penjualan`
  - `Surat Jalan`
  - `Retur Penjualan`
  - `Mutasi Piutang Customer`
- Catatan:
  - field ini dipilih di form transaksi
  - field ini tidak ditampilkan di header print dokumen transaksi
  - field ini dipakai untuk memisahkan analisa transaksi customer antara penjualan produk dan pekerjaan cetak
  - di menu `Piutang`, `Subjenis Cetak` ikut tampil di mutasi, tagihan, print, PDF, dan Excel

## Detail surat pesanan
- Detail `Surat Pesanan` sekarang menampilkan progress per item, bukan hanya total progress
- Di tabel item user/admin bisa melihat:
  - `Qty Pesanan`
  - `Qty Terkirim`
  - `Sisa Qty`
  - `Status`
- Ada card `Rincian Pengiriman per Item`
  - menampilkan barang sudah terkirim lewat invoice nomor berapa
  - tanggal invoice
  - qty yang terkirim di invoice itu
- Informasi yang sama juga ikut ke print/PDF `Surat Pesanan`

## Contoh pesan error yang ramah user
- kalau nama customer diketik tetapi tidak dipilih dari daftar:
  - `Customer tidak terdaftar.`
- kalau nama barang diketik tetapi tidak dipilih dari daftar:
  - `Barang tidak terdaftar.`
- contoh screenshot dan penjelasan lengkapnya sudah dimasukkan ke:
  - `docs/USER_TRANSACTION_GUIDE.md`
  - `docs/ADMIN_TRANSACTION_GUIDE.md`

## Aturan supplier
- `Tanda Terima Barang` harus memilih supplier dari master supplier
- `Bayar Hutang Supplier` juga harus memilih supplier dari master supplier
- supplier tidak boleh diketik bebas lalu langsung disimpan
- kalau supplier belum ada:
  - buat data supplier dulu
  - baru input transaksi keluar atau pembayaran supplier
- lock supplier sekarang per bulan
  - admin bisa menutup atau membuka periode supplier per bulan
  - saat bulan supplier ditutup, user biasa tidak bisa input:
    - `Tanda Terima Barang`
    - `Bayar Hutang Supplier`

## Quick start
1. `composer install`
2. `cp .env.example .env`
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `php artisan db:seed --force`
6. `php artisan serve`

## Redis (opsional)
Secara default aplikasi jalan tanpa Redis (cache/session/queue memakai driver `database`), jadi **tidak wajib**. Redis berguna saat trafik naik atau ingin cache/session/queue lebih cepat. Aplikasi sudah **siap Redis** — saat pindah server cukup ubah `.env`, tidak perlu ubah kode. Client `predis` (pure-PHP) sudah terpasang sehingga **tidak perlu meng-compile extension** apa pun.

### 1. Pasang & jalankan server Redis
- Ubuntu / aaPanel:
  ```bash
  sudo apt update && sudo apt install -y redis-server
  sudo systemctl enable --now redis-server
  redis-cli ping     # harus balas: PONG
  ```
  (Di aaPanel bisa juga lewat App Store → install Redis.)
- Windows / Laragon: Menu → Tools → Redis, atau jalankan `redis-server.exe` dari `laragon/bin/redis/...`.

### 2. Ubah `.env`
```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=predis        # predis = paling mudah (tanpa extension). phpredis = lebih cepat tapi butuh extension PHP "redis"
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null        # isi kalau server Redis pakai password
REDIS_PORT=6379
```
Lalu segarkan cache config:
```bash
php artisan optimize:clear && php artisan config:cache
```

### 3. Jalankan ulang queue worker
Kalau `QUEUE_CONNECTION=redis`, worker harus dijalankan ulang agar membaca queue Redis (lihat bagian Deploy untuk worker permanen):
```bash
php artisan queue:work
```

### 4. Pastikan jalan
```bash
php artisan tinker --execute "Cache::put('t','ok',60); echo Cache::get('t'); echo PHP_EOL.get_class(Cache::store()->getStore());"
```
Harus mencetak `ok` dan `Illuminate\Cache\RedisStore`. Kalau muncul error koneksi, cek server Redis hidup (`redis-cli ping`) dan `REDIS_HOST/PORT/PASSWORD`.

### Redis di server/LXC terpisah (mis. Proxmox)
Kalau Redis berjalan di container/host lain (bukan localhost), aplikasi tidak perlu diubah — cukup arahkan `.env` ke IP-nya:
```env
REDIS_HOST=10.0.0.50        # IP container/LXC Redis
REDIS_PORT=6379
REDIS_PASSWORD=ganti_password_kuat   # WAJIB karena Redis terbuka di jaringan
REDIS_CLIENT=predis
```
Di sisi **container Redis** (`/etc/redis/redis.conf`), untuk aman + tahan lama dengan maintenance minimal:
- `bind 0.0.0.0` (atau IP bridge container) — agar bisa diakses app.
- `protected-mode yes` **dan** `requirepass ganti_password_kuat` — jangan buka Redis tanpa password di jaringan.
- Batasi firewall: hanya izinkan port `6379` dari IP container aplikasi.
- `appendonly yes` (AOF) — data cache/queue/session bertahan saat container restart.
- **Hati-hati `maxmemory-policy`**: kalau Redis ini juga dipakai untuk **queue & session** (bukan cache saja), jangan pakai eviction agresif seperti `allkeys-lru` — job/sesi bisa terbuang saat memori penuh. Untuk satu instance gabungan, biarkan default `noeviction` dan sediakan RAM cukup; kalau mau aman penuh, pisahkan: satu Redis untuk cache (boleh `allkeys-lru`), satu untuk queue/session (`noeviction` + AOF).

Verifikasi dari server aplikasi: `redis-cli -h 10.0.0.50 -a ganti_password_kuat ping` harus balas `PONG`.

### Kembali tanpa Redis
Cukup set ketiga baris kembali ke `database` (atau `file`), lalu `php artisan optimize:clear && php artisan config:cache`.

## Akun default setelah seed
- Admin
  - username: `admin`
  - email: `admin@pgpos.local`
  - password: `@Passwordadmin123#`
- User
  - username: `user`
  - email: `user@pgpos.local`
  - password: `@Passworduser123#`

Login sekarang bisa memakai:
- email + password
- atau username + password

Catatan:
- panduan user/admin, deploy, dan generator PDF memakai akun default yang sama
- kalau password default diubah lagi, sinkronkan juga ke dokumen dan `scripts/generate_manual_pdfs.mjs`

## Hardening yang tersedia
- RBAC detail (`config/rbac.php`) + middleware `perm:*`
- Audit log dengan filter dokumen + before/after
- Import massal barang/customer/supplier + template
- Idempotency middleware untuk cegah submit ganda
- Backup harian + restore test mingguan + integrity check terjadwal
- CI minimal (`.github/workflows/tests.yml`)
- Environment template:
  - `.env.example` (dev)
  - `.env.staging.example`
  - `.env.production.example`

## Command operasional
- Backup DB: `php artisan app:db-backup --gzip`
- Uji restore: `php artisan app:db-restore-test`
- Integrity check: `php artisan app:integrity-check`
- Refresh list update `Sistem > About`: `php artisan app:about-updates-refresh`
- Untuk `erpos` dengan `AWS Lightsail Managed MySQL`, command backup/restore tetap dijalankan dari folder project Laravel di app server aaPanel, tetapi target database-nya tetap koneksi managed DB yang aktif di `.env`
- Untuk alur arsip production, backup dibuat dulu di server lalu **diunduh/salin juga ke komputer lokal operator** sebagai arsip tambahan
- Untuk arsip data production, pola yang disepakati adalah **semi-manual**:
  - backup dulu
  - restore drill dulu
  - lalu arsip / pembersihan data
- Arsip transaksi ERP sekarang bisa dipilih **berdasarkan tahun atau semester**
- Command arsip yang tersedia sekarang:
  - `php artisan app:archive:scan 2021 --dataset=sales_invoices`
  - `php artisan app:archive:scan --semester=S1-2526 --dataset=sales_invoices`
  - `php artisan app:archive:export 2021 --dataset=sales_invoices`
  - `php artisan app:archive:export --semester=S1-2526 --dataset=sales_returns`
  - `php artisan app:archive:prepare-financial 2021 --dataset=sales_invoices --rebuild-journal`
  - `php artisan app:archive:prepare-financial --semester=S1-2526 --dataset=receivable_payments --rebuild-journal`
  - `php artisan app:archive:review`
  - `php artisan app:system-logs-cleanup`
  - `php artisan app:archive:purge 2021 --dataset=sales_returns --confirm`
  - `php artisan app:archive:purge --semester=S1-2526 --dataset=sales_returns --confirm`
- Catatan penting:
  - `Sistem > Arsip Data` sekarang fokus untuk data bisnis saja: `scan`, `export`, `prepare financial snapshot`, dan `purge`
  - `Sistem > Arsip Data` sekarang juga bisa pilih basis arsip `Tahun` atau `Semester`
  - halaman `Sistem > Arsip Data` juga sudah menampilkan histori eksekusi arsip, review arsip terakhir, dan checklist UAT arsip nyata di server
  - log sistem seperti `audit_logs`, `report_export_tasks`, `failed_jobs`, `job_batches`, `integrity_check_logs`, `performance_probe_logs`, dan `restore_drill_logs` sekarang dibersihkan otomatis oleh scheduler lewat `app:system-logs-cleanup`
  - `scan` dan `export` sudah bisa dipakai untuk dataset transaksi ERP berbasis tahun maupun semester
  - `purge` finansial tahap lanjut dibuka untuk dataset yang sudah punya guard snapshot + rebuild, yaitu `sales_invoices`, `sales_returns`, `outgoing_transactions`, `receivable_payments`, dan `supplier_payments`
  - dataset finansial lain seperti `receivable_ledgers` dan `supplier_ledgers` tetap dikunci
  - review arsip bulanan sekarang bisa dijalankan manual dengan `app:archive:review` dan juga sudah dijadwalkan otomatis lewat scheduler Laravel
- Profiling query plan manual: `php artisan app:query-profile`
- Performance probe / load test ringan: `php artisan app:load-test-light --loops=50`
- Sinkronisasi ulang saldo customer/supplier jika integrity check anomali: `php artisan app:financial-rebuild`
- Smoke test menu/sub-menu untuk lokal / development:
  - `php artisan test tests/Feature/PageLoadSmokeTest.php --stop-on-failure`
  - `php artisan test tests/Feature/ActionSmokeTest.php --stop-on-failure`
  - cocok dipakai kalau environment masih punya `dev dependencies`
- Deploy check sekali jalan untuk server production:
  - `php artisan app:deploy-check --skip-ops`
  - ini jalur yang disarankan setelah `composer install --no-dev`
  - kalau `artisan test` tidak tersedia di server, command ini akan fallback ke `app:http-smoke-test`
- Deploy check lengkap:
  - `php artisan app:deploy-check`
  - menjalankan `app:smoke-test` + smoke test halaman menu/sub-menu + dokumen detail/print/PDF/Excel + report print/PDF/Excel + lookup/preview action penting
  - cocok dipakai kalau server memang siap untuk ops check juga

## SOP
- `docs/ops-runbook.md`
- `docs/user-admin-sop.md`
- `docs/USER_TRANSACTION_GUIDE.md`
- `docs/ADMIN_TRANSACTION_GUIDE.md`
- `docs/BACKUP_OPS_HEALTH_README.md`
- `docs/RECOVERY_SOP.md`
- `docs/DEPLOY_AAPANEL.md`
- `docs/DEPLOY_AAPANEL_PROD_AWS_MANAGED_DB.md`
- `docs/RETENTION_POLICY_AAPANEL_PROD_AWS_MANAGED_DB.md`
- `docs/DEPLOY_AWS_LIGHTSAIL_CLOUDPANEL.md`
- `docs/UAT_AAPANEL_POST_DEPLOY.md`
- `docs/GO_LIVE_RUNBOOK.md`

## Import massal dengan header Indonesia
- Template import sekarang memakai header yang lebih natural untuk user awam.
- Contoh header import `Barang`:
  - `kode`
  - `nama`
  - `kategori`
  - `satuan`
  - `stok`
  - `harga_agen`
  - `harga_sales`
  - `harga_umum`
- Kolom `kode` pada import barang boleh kosong.
  - kalau kosong, sistem akan membuat kode barang otomatis
- Import `Customer`, `Supplier`, `Kategori Barang`, `Lokasi Kirim Customer`, dan `Faktur Penjualan` juga sudah menerima header Indonesia
- Kalau ada kolom wajib yang belum lengkap, sistem sekarang menampilkan pesan yang mudah dibaca, misalnya:
  - `Kolom wajib pada file import belum lengkap: Kategori, Harga Umum. Gunakan template import terbaru.`

## Deploy yang direkomendasikan
- Untuk `teserpos` di `aaPanel v8.0.1`, gunakan dokumen ini:
  - `docs/DEPLOY_AAPANEL.md`
  - `docs/UAT_AAPANEL_POST_DEPLOY.md`
  - `docs/BACKUP_OPS_HEALTH_README.md`
  - `docs/GO_LIVE_RUNBOOK.md`
- Untuk `erpos` production di `aaPanel v8.0.1` dengan `AWS Lightsail Managed Database`, gunakan dokumen ini:
  - `docs/DEPLOY_AAPANEL_PROD_AWS_MANAGED_DB.md`
  - `docs/RETENTION_POLICY_AAPANEL_PROD_AWS_MANAGED_DB.md`
  - `docs/BACKUP_OPS_HEALTH_README.md`
  - `docs/GO_LIVE_RUNBOOK.md`
- Di aplikasi, submenu operasionalnya ada di:
  - `Sistem > Arsip Data`
  - halaman ini merangkum prinsip arsip berbasis tahun dan command backup/restore yang relevan untuk managed DB AWS
- Untuk `AWS Lightsail + CloudPanel + Cloudflare`, gunakan dokumen ini:
  - `docs/DEPLOY_AWS_LIGHTSAIL_CLOUDPANEL.md`

Catatan aaPanel:
- dokumentasi deploy sekarang dipisah jadi 2 jalur:
  - `teserpos` di aaPanel dengan DB lokal server
  - `erpos` production di aaPanel dengan managed DB terpisah
- dokumentasi deploy `teserpos` mencakup 2 metode:
  - `Terminal + git clone`
  - `Website -> Add site -> Create for Git`
  - dan section khusus `Update program / upgrade aplikasi`
- untuk repo ini, branch deploy yang dipakai di server adalah:
  - `master`
  - bukan `main`
- dokumentasi juga sudah mencakup setup subdomain untuk skenario:
  - domain di `DomaiNesia`
  - VPS `aaPanel` di `AWS Lightsail`
  - DNS dikelola langsung di `DomaiNesia` atau lewat `Cloudflare`
- file env contoh untuk aaPanel:
  - `.env.aapanel.test.example`
  - `.env.aapanel.prod.example`
- file env contoh untuk CloudPanel:
  - `.env.cloudpanel.test.example`
  - `.env.cloudpanel.prod.example`
- contoh nama website yang dipakai di dokumen:
  - `teserpos.mitrasejatiberkah.com` untuk `tes`
  - `erpos.mitrasejatiberkah.com` untuk `prod`
- untuk first deploy, tetap disarankan mulai dari `Terminal + git clone`
- `Create for Git` aman dipakai juga, tapi tetap harus dilanjutkan dengan setup Laravel lengkap

## Queue worker & scheduler (untuk minimal maintenance)
Agar otomasi jalan terus tanpa di-babysit:

**Scheduler (cron, tiap menit):**
```cron
* * * * * cd /www/wwwroot/NAMA-SITE && php artisan schedule:run >> /dev/null 2>&1
```
Aplikasi mencatat "detak" scheduler tiap menit. Kalau cron mati, halaman **Sistem > Ops Health** dan banner dashboard admin akan menandai **"Scheduler TIDAK AKTIF"** otomatis.

**Queue worker (disarankan systemd, auto-restart):** lebih andal daripada cron `queue:work --stop-when-empty`. Buat `/etc/systemd/system/erpos-worker.service`:
```ini
[Unit]
Description=ERPOS queue worker
After=network.target

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/www/wwwroot/NAMA-SITE
ExecStart=/usr/bin/php artisan queue:work --queue=exports,default --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now erpos-worker
sudo systemctl status erpos-worker
```
Worker yang sama otomatis melayani queue Redis kalau `QUEUE_CONNECTION=redis`. Setelah deploy yang mengubah kode job, restart: `sudo systemctl restart erpos-worker`.

**Notifikasi kegagalan (in-app, admin):** kalau ada tugas terjadwal yang gagal (mis. backup), aplikasi membuat **peringatan sistem** yang muncul sebagai banner di dashboard admin dan daftar di **Ops Health** (bisa "Tandai selesai"). Jadi kegagalan tidak lagi senyap.

## Panduan user/admin siap cetak
- Ringkas siap print:
  - `docs/USER_PRINT_README.md`
  - `docs/ADMIN_PRINT_README.md`
- Markdown:
  - `docs/USER_TRANSACTION_GUIDE.md`
  - `docs/ADMIN_TRANSACTION_GUIDE.md`
- PDF:
  - `docs/USER_TRANSACTION_GUIDE.pdf`
  - `docs/ADMIN_TRANSACTION_GUIDE.pdf`

Regenerate screenshot + PDF:

```bash
npm install --no-save playwright marked
npx playwright install chromium
node scripts/generate_manual_pdfs.mjs
```
