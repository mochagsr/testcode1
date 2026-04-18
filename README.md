# PgPOS ERP

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
  - `php artisan app:archive:purge 2021 --dataset=audit_logs --confirm`
  - `php artisan app:archive:purge --semester=S1-2526 --dataset=sales_returns --confirm`
- Catatan penting:
  - `Sistem > Arsip Data` sekarang sudah punya form aksi untuk `scan`, `export`, `prepare financial snapshot`, dan `purge`
  - `Sistem > Arsip Data` sekarang juga bisa pilih basis arsip `Tahun` atau `Semester`
  - halaman `Sistem > Arsip Data` juga sudah menampilkan histori eksekusi arsip, review arsip terakhir, dan checklist UAT arsip nyata di server
  - `scan` dan `export` sudah bisa dipakai untuk dataset transaksi ERP berbasis tahun maupun semester
  - `purge` biasa sudah dibuka untuk dataset log/ops aman, termasuk `failed_jobs` dan `job_batches`
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

