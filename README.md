# PgPOS ERP

ERP berbasis Laravel untuk distribusi/penerbitan dengan modul:
- Master data: barang, customer, supplier
- Transaksi: faktur penjualan, retur, surat jalan, surat pesanan, transaksi keluar
- Piutang customer + hutang supplier + mutasi
- Report print/PDF/Excel
- Audit log finansial

## Quick start
1. `composer install`
2. `cp .env.example .env`
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `php artisan db:seed --force`
6. `php artisan serve`

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
