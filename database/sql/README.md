# SQL Deployment Files

File di folder ini dipakai untuk bantu inisialisasi MySQL saat deploy `tes` dan `prod`.

## File

- `tespgpos_mysql_test_snapshot.sql`
  - snapshot MySQL dari database SQLite lokal yang sedang dipakai
  - pakai ini untuk deploy `tes`
- `tespgpos_mysql_prod_bootstrap.sql`
  - schema + data seed dasar hasil migrasi MySQL
  - pakai ini untuk bootstrap `prod`
- `tespgpos_mysql_bootstrap.sql`
  - file lama kompatibilitas
  - isinya sama dengan bootstrap production

## Catatan

- jalur paling aman tetap `php artisan migrate --force`
- file SQL ini dipakai kalau kamu ingin import langsung dari phpMyAdmin / Adminer / MySQL CLI
- untuk regenerate snapshot `tes` dari SQLite lokal, jalankan:

```bash
php artisan app:sqlite-to-mysql-snapshot
```

- untuk regenerate bootstrap `prod` dari migrasi + seed terbaru, jalankan:

```bash
php artisan app:mysql-prod-bootstrap
```

- setelah import SQL, tetap jalankan:

```bash
php artisan optimize:clear
```
