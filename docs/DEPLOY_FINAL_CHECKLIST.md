# Checklist Deploy Final cPanel

Dokumen ini dipakai untuk 2 tahap:

- deploy `tes`
- deploy `prod`

## A. Data yang harus disiapkan dulu

Isi data ini sebelum deploy:

- `CPANEL_USERNAME`
- `REPO_URL`
- `REPO_BRANCH`
- `TES_DOMAIN`
- `PROD_DOMAIN`
- `TES_DOCROOT`
- `PROD_DOCROOT`
- `TES_DB_NAME`
- `TES_DB_USER`
- `TES_DB_PASSWORD`
- `PROD_DB_NAME`
- `PROD_DB_USER`
- `PROD_DB_PASSWORD`

Contoh:

- `TES_DOMAIN=tes.example.com`
- `PROD_DOMAIN=erp.example.com`
- `TES_DOCROOT=/home/USER/repositories/tespgpos/public`
- `PROD_DOCROOT=/home/USER/repositories/tespgpos/public`

## B. Checklist deploy `tes`

### 1. cPanel

- buat subdomain/domain `tes`
- arahkan document root ke folder `public`
- buat database MySQL `tes`
- buat user MySQL `tes`
- assign `ALL PRIVILEGES`

### 2. Code

- clone repo dari GitHub
- checkout branch yang benar
- jalankan `composer install --no-dev --optimize-autoloader`

### 3. Environment

- copy `.env.cpanel.test.example` menjadi `.env`
- isi semua placeholder
- jalankan `php artisan key:generate`

### 4. Database

- import `database/sql/tespgpos_mysql_test_snapshot.sql`
- lalu jalankan:
  - `php artisan db:seed --force`
- kalau snapshot `tes` ingin disegarkan lagi dari data lokal terbaru:
  - jalankan lokal `php artisan app:sqlite-to-mysql-snapshot`

### 5. Optimasi

- `php artisan storage:link`
- `php artisan optimize:clear`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan event:cache`
- `php artisan view:cache`

### 6. Cron

- schedule:run tiap 1 menit
- queue:work --stop-when-empty tiap 1 menit

### 7. Smoke test `tes`

- login admin berhasil
- dashboard tampil
- barang tampil
- customer tampil
- piutang tampil
- bisa buat 1 faktur
- bisa print report
- bisa export PDF
- bisa export Excel
- upload logo/file berhasil
- queue export berjalan

## C. Checklist deploy `prod`

### 1. cPanel

- buat domain/subdomain `prod`
- arahkan document root ke folder `public`
- buat database MySQL `prod`
- buat user MySQL `prod`
- assign `ALL PRIVILEGES`

### 2. Code

- pull branch final dari GitHub
- jalankan `composer install --no-dev --optimize-autoloader`

### 3. Environment

- copy `.env.cpanel.prod.example` menjadi `.env`
- isi semua placeholder
- review dengan dokumen `docs/ENV_PRODUCTION_REVIEW_TEMPLATE.md`
- jalankan `php artisan key:generate`

### 4. Database

Pilih salah satu:

- rekomendasi:
  - `php artisan migrate --force`
  - `php artisan db:seed --force`
- atau import:
  - `database/sql/tespgpos_mysql_prod_bootstrap.sql`
- kalau bootstrap `prod` ingin disegarkan lagi dari migrasi + seeder terbaru:
  - jalankan lokal `php artisan app:mysql-prod-bootstrap`

### 5. Optimasi

- `php artisan storage:link`
- `php artisan optimize:clear`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan event:cache`
- `php artisan view:cache`

### 6. Cron

- schedule:run tiap 1 menit
- queue:work --stop-when-empty tiap 1 menit

### 7. Smoke test `prod`

- login berhasil
- print report berhasil
- PDF berhasil
- Excel berhasil
- transaksi faktur berhasil
- bayar piutang berhasil
- hutang supplier berhasil
- backup command berhasil
- restore drill command berhasil

## D. Checklist sebelum pindah dari `tes` ke `prod`

- data `tes` sudah diverifikasi
- print report sudah sesuai printer
- queue export sudah stabil
- cron jalan normal
- `.env` `prod` sudah direview
- database backup `prod` sudah ada
- maintenance window sudah disiapkan

## E. Checklist sesudah deploy `prod`

- backup awal berhasil dibuat
- test login admin
- test login user biasa
- test 1 transaksi
- test 1 print
- test 1 PDF
- test 1 Excel
- cek `ops health`
- cek `audit log`
- cek `queue jobs`
