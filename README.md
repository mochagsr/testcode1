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
- Profiling query list: `php artisan app:query-profile`
- Load test ringan: `php artisan app:load-test-light --loops=50`

## SOP
- `docs/ops-runbook.md`
- `docs/user-admin-sop.md`
- `docs/USER_TRANSACTION_GUIDE.md`
- `docs/ADMIN_TRANSACTION_GUIDE.md`
- `docs/BACKUP_OPS_HEALTH_README.md`
- `docs/RECOVERY_SOP.md`
- `docs/DEPLOY_AAPANEL.md`
- `docs/UAT_AAPANEL_POST_DEPLOY.md`
- `docs/GO_LIVE_RUNBOOK.md`

## Deploy yang direkomendasikan
- Untuk panel `aaPanel v8.0.1`, gunakan dokumen ini:
  - `docs/DEPLOY_AAPANEL.md`
  - `docs/UAT_AAPANEL_POST_DEPLOY.md`
  - `docs/BACKUP_OPS_HEALTH_README.md`
  - `docs/GO_LIVE_RUNBOOK.md`

Catatan aaPanel:
- dokumentasi deploy sekarang mencakup 2 metode:
  - `Terminal + git clone`
  - `Website -> Add site -> Create for Git`
- untuk first deploy, tetap disarankan mulai dari `Terminal + git clone`
- `Create for Git` aman dipakai juga, tapi tetap harus dilanjutkan dengan setup Laravel lengkap

## Panduan user/admin siap cetak
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
