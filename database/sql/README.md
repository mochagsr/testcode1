# SQL Bootstrap

File di folder ini dipakai untuk bantu inisialisasi MySQL saat uji deploy.

## File

- `tespgpos_mysql_bootstrap.sql`
  - schema + data seed dasar hasil migrasi MySQL
  - cocok untuk deploy test awal

## Catatan

- jalur paling aman tetap `php artisan migrate --force`
- file SQL ini dipakai kalau kamu ingin import langsung dari phpMyAdmin / Adminer / MySQL CLI
- setelah import SQL, tetap jalankan:

```bash
php artisan optimize:clear
```
