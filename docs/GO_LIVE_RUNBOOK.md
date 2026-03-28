# Go-Live Runbook

Checklist singkat saat pindah dari `tes` ke `prod`.

## Sebelum live
- deploy `tes` terakhir lolos UAT
- backup `prod` sudah ada
- file `.env` `prod` sudah direview
- queue worker dan cron siap

## Saat live
1. backup DB `prod`
2. `git pull`
3. `composer install --no-dev --optimize-autoloader`
4. `npm install && npm run build` jika ada perubahan asset
5. `php artisan migrate --force`
6. `php artisan optimize:clear`
7. `php artisan config:cache`
8. `php artisan route:cache`
9. `php artisan event:cache`
10. `php artisan view:cache`

## Setelah live
- login admin
- cek dashboard
- cek ops health
- buat 1 transaksi uji
- cek print/PDF/Excel
- cek audit log
- kalau perlu import data, gunakan `docs/IMPORT_MASSAL_PLAYBOOK.md`

## Rollback minimum
- restore DB backup terakhir
- rollback code ke commit stabil terakhir
- clear cache aplikasi
