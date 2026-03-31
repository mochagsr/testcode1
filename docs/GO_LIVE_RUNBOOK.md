# Go-Live Runbook (aaPanel v8.0.1)

Checklist singkat saat pindah dari `tes` ke `prod`.

## Akun default setelah seed
- Admin
  - username: `admin`
  - email: `admin@pgpos.local`
  - password: `@Passwordadmin123#`
- User
  - username: `user`
  - email: `user@pgpos.local`
  - password: `@Passworduser123#`

Login bisa memakai username atau email.

## Sebelum live
- deploy `tes` terakhir lolos UAT
- backup `prod` sudah ada
- file `.env` `prod` sudah direview
- queue worker dan cron siap
- di aaPanel:
  - `Website` aktif
  - `Running directory = public`
  - `PHP 8.3` benar
  - `Cron` aktif

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
11. `php artisan app:db-restore-test`
12. `php artisan app:smoke-test`

## Setelah live
- login admin
- cek dashboard
- cek ops health
- buat 1 transaksi uji
- cek print/PDF/Excel
- cek audit log
- kalau perlu import data, gunakan `docs/IMPORT_MASSAL_PLAYBOOK.md`

## Contoh command live

```bash
cd /www/wwwroot/pgpos-prod
php artisan app:db-backup
git pull origin master
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-restore-test
php artisan app:smoke-test
```

## Rollback minimum
- restore DB backup terakhir
- rollback code ke commit stabil terakhir
- clear cache aplikasi
