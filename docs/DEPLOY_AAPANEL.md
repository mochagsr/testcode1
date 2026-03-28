# Deploy PgPOS ERP di aaPanel

Dokumen ini untuk deploy aplikasi ke server Linux yang memakai aaPanel.

## 1. Stack yang dipakai aplikasi

- PHP `8.3`
- Laravel `12`
- MySQL / MariaDB
- Queue driver: `database`
- Session driver: `database`
- Cache store: `database`
- PDF export: `barryvdh/laravel-dompdf`
- Excel export: `phpoffice/phpspreadsheet`

## 2. Minimum server yang layak

Untuk app ini:

- `2 vCPU`
- `2 GB RAM`
- `40 GB SSD`

Rekomendasi lebih aman:

- `4 vCPU`
- `4-8 GB RAM`
- `60-100 GB NVMe`

## 3. Paket yang perlu di-install di aaPanel

Di aaPanel:

1. Install `Nginx`
2. Install `MySQL` atau `MariaDB`
3. Install `PHP 8.3`
4. Install `phpMyAdmin` kalau mau import SQL via browser

## 4. Extension PHP yang wajib aktif

Di menu PHP 8.3 -> Install Extensions:

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

Install via terminal server:

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

- app test:
  - `/www/wwwroot/pgpos-tes`
- app prod:
  - `/www/wwwroot/pgpos-prod`

Document root website harus ke:

- `/www/wwwroot/pgpos-tes/public`
- atau
- `/www/wwwroot/pgpos-prod/public`

## 7. Clone code dari GitHub

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

## 8. Buat database

Di aaPanel:

1. Database -> Add DB
2. buat database `tes`
3. buat database `prod`
4. simpan:
   - host
   - db name
   - username
   - password

Biasanya host:

- `127.0.0.1`
- atau `localhost`

## 9. Siapkan `.env`

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

## 10. Inisialisasi database

### Tes

Pakai snapshot lokal terbaru:

- `database/sql/tespgpos_mysql_test_snapshot.sql`

Import via terminal:

```bash
mysql -u DB_USER -p DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
```

Lalu:

```bash
php artisan db:seed --force
```

### Prod

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

## 11. Build asset frontend

App ini mayoritas tetap jalan tanpa Vite build di fitur utama, tapi untuk deploy aman tetap jalankan build sekali:

```bash
npm install
npm run build
```

Kalau build gagal karena Node terlalu lama/outdated, upgrade Node dulu.

## 12. Link storage dan cache production

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## 13. Permission folder

```bash
chown -R www:www /www/wwwroot/pgpos-tes
chown -R www:www /www/wwwroot/pgpos-prod
chmod -R 755 /www/wwwroot/pgpos-tes
chmod -R 755 /www/wwwroot/pgpos-prod
chmod -R 775 /www/wwwroot/pgpos-tes/storage /www/wwwroot/pgpos-tes/bootstrap/cache
chmod -R 775 /www/wwwroot/pgpos-prod/storage /www/wwwroot/pgpos-prod/bootstrap/cache
```

Kalau user web server bukan `www`, sesuaikan.

## 14. Site config di aaPanel

### Root

Set site root ke folder:

- `/www/wwwroot/pgpos-tes/public`
- atau
- `/www/wwwroot/pgpos-prod/public`

### Rewrite

Pakai rewrite Laravel standar:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### SSL

Aktifkan SSL setelah domain/subdomain sudah mengarah.

## 15. Scheduler dan queue di aaPanel

Di Cron aaPanel, buat task:

### Scheduler

- Type: Shell Script
- Every minute

```bash
cd /www/wwwroot/pgpos-prod && php artisan schedule:run >> /dev/null 2>&1
```

Untuk `tes`, ganti path ke folder test.

### Queue worker

Kalau belum pakai supervisor, buat cron juga:

```bash
cd /www/wwwroot/pgpos-prod && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

Kalau bisa, lebih baik pakai Supervisor:

```ini
[program:pgpos-prod-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /www/wwwroot/pgpos-prod/artisan queue:work --sleep=1 --tries=1 --timeout=120
autostart=true
autorestart=true
user=www
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwlogs/pgpos-prod-worker.log
stopwaitsecs=3600
```

## 16. Langkah uji setelah deploy

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

## 17. Update dari GitHub

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

## 19. Cara update aplikasi yang benar

Jangan upload file satu-satu lewat File Manager. Itu rawan:

- file tertinggal
- versi campur
- migration lupa dijalankan
- cache lama tidak bersih

Pola update yang benar:

1. push perubahan dari lokal ke GitHub
2. masuk server aaPanel
3. `git pull`
4. jalankan command update sesuai jenis perubahan

### A. Update kecil

Contoh:

- Blade
- controller
- route
- translation
- CSS inline

Command:

```bash
cd /www/wwwroot/pgpos-prod
git pull origin master
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### B. Update yang ada migration database

Contoh:

- tambah kolom
- ubah tabel
- tambah index

Command:

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

### C. Update yang ada perubahan frontend

Contoh:

- Vite build
- resource CSS/JS berubah

Command:

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

### D. Pola aman untuk `tes` lalu `prod`

Saran operasional:

1. update dulu `tes`
2. uji:
   - login
   - transaksi
   - print
   - PDF
   - Excel
3. kalau aman, baru update `prod`

## 20. Dokumen pendamping yang dipakai saat live

- `docs/UAT_AAPANEL_POST_DEPLOY.md`
- `docs/IMPORT_MASSAL_PLAYBOOK.md`
- `docs/STRESS_TEST_GUIDE.md`
- `docs/GO_LIVE_RUNBOOK.md`

## 21. Saran praktik

- deploy `tes` dulu, jangan langsung `prod`
- pakai DB `tes` dan `prod` terpisah
- backup DB sebelum update
- jangan pakai `APP_DEBUG=true` di server public
- kalau ada 500 error setelah update, jalankan:

```bash
php artisan optimize:clear
```
