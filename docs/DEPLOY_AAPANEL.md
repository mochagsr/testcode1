# Deploy PgPOS ERP di aaPanel v8.0.1

Dokumen ini disiapkan untuk deploy aplikasi ke server Linux yang memakai `aaPanel v8.0.1`.

Istilah menu yang dipakai di dokumen ini mengikuti aaPanel `v8.0.1`:
- `Website`
- `App Store`
- `Databases`
- `Cron`
- `Terminal`
- `Files`

Catatan:
- di dokumentasi resmi aaPanel, website PHP kadang disebut `PHP Project`
- di panel harian kamu biasanya tetap bekerja lewat menu `Website`

## Akun default setelah seed
- Admin
  - username: `admin`
  - email: `admin@pgpos.local`
  - password: `@Passwordadmin123#`
- User
  - username: `user`
  - email: `user@pgpos.local`
  - password: `@Passworduser123#`

Login app mendukung:
- username + password
- atau email + password

Kalau sesudah deploy kamu masih login dengan akun default, segera simpan password final internal perusahaan di tempat aman.

## 1. Stack aplikasi

- PHP `8.3`
- Laravel `12`
- MySQL / MariaDB
- Queue driver: `database`
- Session driver: `database`
- Cache store: `database`
- PDF export: `barryvdh/laravel-dompdf`
- Excel export: `phpoffice/phpspreadsheet`

## 2. Minimum server yang layak

Minimum:
- `2 vCPU`
- `2 GB RAM`
- `40 GB SSD`

Rekomendasi:
- `4 vCPU`
- `4-8 GB RAM`
- `60-100 GB NVMe`

## 3. Paket yang perlu di-install di aaPanel v8.0.1

Buka menu `App Store`, lalu install:

1. `Nginx`
2. `MySQL` atau `MariaDB`
3. `PHP 8.3`
4. `phpMyAdmin` kalau mau import SQL via browser

Tambahan yang saya sarankan:
5. `Redis` opsional
6. `Node.js` kalau mau build langsung di server

## 4. Extension PHP yang wajib aktif

Di aaPanel `v8.0.1`:
- buka `App Store`
- buka `PHP 8.3`
- masuk `Extensions`

Aktifkan:
- `bcmath`
- `ctype`
- `curl`
- `dom`
- `exif`
- `fileinfo`
- `gd`
- `mbstring`
- `mysqli`
- `openssl`
- `pdo`
- `pdo_mysql`
- `tokenizer`
- `xml`
- `zip`

Opsional tapi bagus:
- `intl`
- `opcache`

## 5. Software tambahan di server

Jalankan via `Terminal`:

```bash
apt update
apt install -y git unzip curl supervisor nodejs npm
```

Kalau `composer` belum ada:

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

## 6. Struktur folder yang disarankan

Contoh:
- env `tes`:
  - `/www/wwwroot/pgpos-tes`
- env `prod`:
  - `/www/wwwroot/pgpos-prod`

Running directory website harus ke:
- `/www/wwwroot/pgpos-tes/public`
- `/www/wwwroot/pgpos-prod/public`

## 7. Metode deploy yang tersedia di aaPanel v8.0.1

Ada 2 jalur yang layak dipakai:

1. `Terminal + git clone`
2. `Website -> Add site -> Create for Git`

Rekomendasi:
- **deploy pertama**: pakai `Terminal + git clone`
- **deploy berikutnya / update rutin**: boleh tetap manual, atau pakai `Create for Git`

Kenapa `Terminal + git clone` saya jadikan default saat first deploy:
- lebih mudah kontrol folder `tes` dan `prod`
- lebih mudah lihat error `composer`, `artisan`, dan `npm build`
- lebih mudah pastikan `.env`, DB, cache, queue, dan cron benar

Kenapa `Create for Git` tetap layak:
- clone repo jadi lebih cepat dari UI
- cocok kalau tim ingin semua site dibuat langsung dari menu `Website`
- bisa dipakai untuk update `pull` berikutnya dari panel

Catatan penting:
- **`Create for Git` tidak membuat app Laravel langsung siap pakai**
- setelah clone, kamu tetap harus menjalankan:
  - `composer install`
  - isi `.env`
  - `php artisan key:generate`
  - import DB / `migrate`
  - `npm run build`
  - `storage:link`
  - cache
  - cron / queue

## 7A. Opsi A - Rekomendasi: `Terminal + git clone`

Pakai ini kalau:
- ini deploy pertama
- kamu ingin kontrol penuh path project
- kamu ingin minim kejutan saat debug

Alur:
1. buat site di `Website`
2. arahkan path ke folder project
3. clone repo via `Terminal`
4. lanjut setup Laravel

Contoh:
- `tes` -> `/www/wwwroot/pgpos-tes`
- `prod` -> `/www/wwwroot/pgpos-prod`

## 7B. Opsi B - `Website -> Add site -> Create for Git`

Pakai ini kalau:
- kamu nyaman membuat site sekaligus clone repo dari UI aaPanel
- kamu tetap siap masuk `Terminal` untuk langkah Laravel sesudah clone

Langkah di aaPanel `v8.0.1`:
1. buka `Website`
2. klik `Add site`
3. pilih opsi `Create for Git`
4. isi:
   - `Domain`
   - `Project path`
   - `PHP Version` = `PHP 8.3`
   - `Git Repository URL`
   - branch, biasanya `master`
5. simpan

Contoh isian:
- `Domain`:
  - `tes.domainkamu.com`
- `Project path`:
  - `/www/wwwroot/pgpos-tes`
- `Git Repository URL`:
  - `https://github.com/mochagsr/testcode1.git`
- `Branch`:
  - `master`

Setelah site berhasil dibuat lewat `Create for Git`, lanjutkan:
1. buka detail site
2. masuk `Site directory`
3. set `Running directory = public`
4. masuk `Terminal`
5. jalankan langkah Laravel seperti biasa

Contoh untuk env `tes` setelah `Create for Git`:

```bash
cd /www/wwwroot/pgpos-tes
composer install --no-dev --optimize-autoloader
cp .env.cpanel.test.example .env
php artisan key:generate
mysql -u TES_DB_USER -p TES_DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

Contoh untuk env `prod` setelah `Create for Git`:

```bash
cd /www/wwwroot/pgpos-prod
composer install --no-dev --optimize-autoloader
cp .env.cpanel.prod.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

Ringkasnya:
- `Create for Git` hanya menggantikan langkah clone repo
- semua langkah Laravel setelah itu **tetap wajib**

## 8A. Buat website di aaPanel untuk Opsi A

Ikuti ini kalau kamu memilih `Terminal + git clone`.

Langkah:
1. buka `Website`
2. klik `Add site`
3. isi domain / subdomain
4. pilih `PHP 8.3`
5. set path:
   - `/www/wwwroot/pgpos-tes`
   - atau `/www/wwwroot/pgpos-prod`
6. simpan

Setelah site dibuat:
1. buka site tersebut
2. masuk `Site directory`
3. set `Running directory = public`

## 9A. Clone code dari GitHub untuk Opsi A

### Env tes

```bash
cd /www/wwwroot
git clone https://github.com/mochagsr/testcode1.git pgpos-tes
cd /www/wwwroot/pgpos-tes
composer install --no-dev --optimize-autoloader
```

### Env prod

```bash
cd /www/wwwroot
git clone https://github.com/mochagsr/testcode1.git pgpos-prod
cd /www/wwwroot/pgpos-prod
composer install --no-dev --optimize-autoloader
```

## 10A. Buat database untuk Opsi A

Di aaPanel `v8.0.1`:
1. buka `Databases`
2. klik `Add DB`
3. buat database `tes`
4. buat database `prod`
5. simpan:
   - host
   - db name
   - username
   - password

Biasanya host:
- `127.0.0.1`
- atau `localhost`

## 11A. Siapkan `.env` untuk Opsi A

### Tes

```bash
cd /www/wwwroot/pgpos-tes
cp .env.cpanel.test.example .env
php artisan key:generate
```

### Prod

```bash
cd /www/wwwroot/pgpos-prod
cp .env.cpanel.prod.example .env
php artisan key:generate
```

Field minimal yang harus diisi:

```env
APP_NAME="PgPOS ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-kamu.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_database

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
FILESYSTEM_DISK=public
```

## 12A. Inisialisasi database untuk Opsi A

### Env tes

```bash
mysql -u DB_USER -p DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
```

### Env prod

Pilihan aman:

```bash
php artisan migrate --force
php artisan db:seed --force
```

Atau import bootstrap:

```bash
mysql -u DB_USER -p DB_NAME < database/sql/tespgpos_mysql_prod_bootstrap.sql
php artisan db:seed --force
```

## 13A. Build asset frontend untuk Opsi A

```bash
npm install
npm run build
```

## 14A. Link storage dan cache production untuk Opsi A

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## 15A. Scheduler dan queue untuk Opsi A

Di aaPanel `v8.0.1`:
- buka `Cron`
- tambah task baru
- type: `Shell Script`

Scheduler `prod`:

```bash
cd /www/wwwroot/pgpos-prod && php artisan schedule:run >> /dev/null 2>&1
```

Queue `prod`:

```bash
cd /www/wwwroot/pgpos-prod && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

## 16A. Backup awal dan smoke test untuk Opsi A

### Env tes

```bash
cd /www/wwwroot/pgpos-tes
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot/pgpos-prod
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

Catatan:
- backup awal lebih aman **tanpa `--gzip`**
- restore drill otomatis mencari file `.sql`

## 17A. Contoh alur lengkap Opsi A

### Env tes

```bash
cd /www/wwwroot
git clone https://github.com/mochagsr/testcode1.git pgpos-tes
cd /www/wwwroot/pgpos-tes
composer install --no-dev --optimize-autoloader
cp .env.cpanel.test.example .env
php artisan key:generate
mysql -u TES_DB_USER -p TES_DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot
git clone https://github.com/mochagsr/testcode1.git pgpos-prod
cd /www/wwwroot/pgpos-prod
composer install --no-dev --optimize-autoloader
cp .env.cpanel.prod.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

## 8B. Buat website lewat `Create for Git` untuk Opsi B

Ikuti ini kalau kamu memilih `Website -> Add site -> Create for Git`.

Langkah:
1. buka `Website`
2. klik `Add site`
3. pilih `Create for Git`
4. isi:
   - `Domain`
   - `Project path`
   - `PHP Version` = `PHP 8.3`
   - `Git Repository URL`
   - `Branch` = `master`
5. simpan

Contoh:
- `tes.domainkamu.com`
- path `/www/wwwroot/pgpos-tes`
- repo `https://github.com/mochagsr/testcode1.git`

## 9B. Cek running directory untuk Opsi B

Setelah site selesai dibuat:
1. buka detail site
2. masuk `Site directory`
3. set `Running directory = public`

## 10B. Buat database untuk Opsi B

Di aaPanel `v8.0.1`:
1. buka `Databases`
2. klik `Add DB`
3. buat database `tes`
4. buat database `prod`
5. simpan host, db name, username, dan password

## 11B. Siapkan `.env` untuk Opsi B

### Tes

```bash
cd /www/wwwroot/pgpos-tes
cp .env.cpanel.test.example .env
php artisan key:generate
```

### Prod

```bash
cd /www/wwwroot/pgpos-prod
cp .env.cpanel.prod.example .env
php artisan key:generate
```

Field minimal yang harus diisi sama seperti `11A`.

## 12B. Inisialisasi database untuk Opsi B

### Env tes

```bash
mysql -u DB_USER -p DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
```

### Env prod

```bash
php artisan migrate --force
php artisan db:seed --force
```

## 13B. Build asset frontend untuk Opsi B

```bash
npm install
npm run build
```

## 14B. Link storage dan cache production untuk Opsi B

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## 15B. Scheduler dan queue untuk Opsi B

Scheduler `prod`:

```bash
cd /www/wwwroot/pgpos-prod && php artisan schedule:run >> /dev/null 2>&1
```

Queue `prod`:

```bash
cd /www/wwwroot/pgpos-prod && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

## 16B. Backup awal dan smoke test untuk Opsi B

### Env tes

```bash
cd /www/wwwroot/pgpos-tes
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot/pgpos-prod
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

## 17B. Contoh alur lengkap Opsi B

### Env tes

```bash
cd /www/wwwroot/pgpos-tes
composer install --no-dev --optimize-autoloader
cp .env.cpanel.test.example .env
php artisan key:generate
mysql -u TES_DB_USER -p TES_DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot/pgpos-prod
composer install --no-dev --optimize-autoloader
cp .env.cpanel.prod.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

## 18. Langkah uji setelah deploy

Uji minimal:
1. login admin
2. buka dashboard
3. buka barang
4. buka customer
5. buka piutang
6. buat 1 faktur
7. print 1 report
8. export PDF
9. export Excel
10. upload logo / file
11. cek queue export

## 19. Cara update aplikasi yang benar

Jangan upload file satu-satu lewat `Files`.

Pola update yang benar:
1. push perubahan dari lokal ke GitHub
2. masuk server aaPanel
3. `git pull`
4. jalankan command update sesuai jenis perubahan

### Update kecil

```bash
cd /www/wwwroot/pgpos-prod
git pull origin master
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Update dengan migration

```bash
cd /www/wwwroot/pgpos-prod
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

### Update dengan frontend build

```bash
cd /www/wwwroot/pgpos-prod
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
```

### Pola aman `tes` lalu `prod`

1. update dulu `tes`
2. uji login, transaksi, print, PDF, dan Excel
3. kalau aman, baru update `prod`

## 20. Dokumen pendamping

- `docs/UAT_AAPANEL_POST_DEPLOY.md`
- `docs/IMPORT_MASSAL_PLAYBOOK.md`
- `docs/STRESS_TEST_GUIDE.md`
- `docs/GO_LIVE_RUNBOOK.md`
- `docs/BACKUP_OPS_HEALTH_README.md`

## 21. Saran praktik

- deploy `tes` dulu, jangan langsung `prod`
- pakai DB `tes` dan `prod` terpisah
- backup DB sebelum update
- jangan pakai `APP_DEBUG=true` di server public
- kalau ada 500 error setelah update, jalankan:

```bash
php artisan optimize:clear
```
