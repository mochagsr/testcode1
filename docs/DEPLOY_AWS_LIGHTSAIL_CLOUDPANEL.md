# Deploy PgPOS ERP di AWS Lightsail + CloudPanel

Dokumen ini disiapkan untuk deploy aplikasi ke server Linux yang memakai:
- `AWS Lightsail`
- `CloudPanel`
- DNS dikelola lewat `Cloudflare`

Dokumen ini meniru alur panduan `aaPanel`, tetapi disesuaikan untuk:
- `teserpos.mitrasejatiberkah.com` sebagai env `tes`
- `erpos.mitrasejatiberkah.com` sebagai env `prod`

Istilah menu yang dipakai di dokumen ini mengikuti CloudPanel:
- `Sites`
- `Databases`
- `Cron Jobs`
- `Users`
- `SSL/TLS`
- `File Manager`
- `Logs`
- `Settings`

Catatan:
- di dokumen ini, contoh OS yang dipakai adalah `Ubuntu 24.04 LTS`
- kamu tetap bisa pakai `Ubuntu 22.04 LTS`, tetapi contoh command di bawah ditulis dengan asumsi `Ubuntu 24.04 LTS`
- untuk env CloudPanel, dokumen ini memakai file contoh:
  - `.env.cloudpanel.test.example`
  - `.env.cloudpanel.prod.example`

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

Kalau sesudah deploy kamu masih login dengan akun default, segera ganti password final internal perusahaan dan simpan di tempat aman.

## 1. Stack aplikasi

- PHP `8.3`
- Laravel `12`
- MySQL / MariaDB
- Queue driver: `database`
- Session driver: `database`
- Cache store: `database`
- PDF export: `barryvdh/laravel-dompdf`
- Excel export: `phpoffice/phpspreadsheet`

## 2. Paket server yang layak di AWS Lightsail

Kalau hanya untuk `tes`:
- minimum:
  - `2 vCPU`
  - `4 GB RAM`
  - `80 GB SSD`
- rekomendasi:
  - `2 vCPU`
  - `8 GB RAM`
  - `160 GB SSD`

Kalau satu instance akan dipakai untuk:
- `teserpos`
- `erpos`
- panel CloudPanel
- cron, queue, print, export, dan backup

rekomendasi realistis:
- `4 vCPU`
- `8 GB RAM`
- `160 GB SSD`

Kalau traffic dan export cukup sering:
- `4 vCPU`
- `16 GB RAM`
- `240 GB SSD`

## 3. Persiapan instance AWS Lightsail

### 3.1. Buat instance baru

Di `AWS Lightsail`:
1. buat `Linux/Unix instance`
2. pilih:
   - `Ubuntu 24.04 LTS`
3. beri nama instance yang jelas, misalnya:
   - `pgpos-cloudpanel-01`

### 3.2. Buat dan attach static IP

Jangan deploy ke IP dinamis.

Di `Lightsail`:
1. buka instance
2. buka tab `Networking`
3. buat `Static IP`
4. attach ke instance `pgpos-cloudpanel-01`

Catat IP public final.

Contoh:
- `18.141.23.45`

IP ini yang nanti dipakai di `Cloudflare`.

### 3.3. Firewall / networking Lightsail

Pastikan port berikut dibuka:
- `22` untuk SSH
- `80` untuk HTTP
- `443` untuk HTTPS
- `8443` untuk panel CloudPanel

Saran praktik:
- `22` sebaiknya dibatasi ke IP kantor / IP admin kalau memungkinkan
- `8443` sebaiknya juga dibatasi ke IP admin kalau memungkinkan
- `3306` tidak perlu dibuka ke publik

## 4. Install CloudPanel di instance

Masuk ke server:

```bash
ssh ubuntu@YOUR_STATIC_IP
sudo -i
```

Update paket:

```bash
apt update
apt upgrade -y
apt install -y curl wget sudo git unzip ca-certificates
```

Install CloudPanel.

Pilih database engine yang kamu inginkan. Untuk contoh ini kita pakai `MariaDB`.

Catatan penting:
- walaupun server memakai `MariaDB`, file `.env` aplikasi Laravel tetap memakai:
  - `DB_CONNECTION=mysql`
- ini normal
- driver `mysql` di Laravel / PDO dipakai juga untuk koneksi ke `MariaDB`

```bash
curl -sSL https://installer.cloudpanel.io/ce/v2/install.sh | DB_ENGINE=MARIADB_11.4 bash
```

Catatan:
- installer CloudPanel butuh server bersih
- jangan install panel lain di instance yang sama
- setelah install selesai, reboot server sekali kalau diminta

## 5. Login pertama ke CloudPanel

Setelah install selesai, buka:

```text
https://YOUR_STATIC_IP:8443
```

Lalu buat akun admin panel.

Contoh:
- email panel:
  - `admin@mitrasejatiberkah.com`
- password panel:
  - password kuat internal

Catat dengan aman.

## 6. Domain panel CloudPanel opsional

Kalau kamu ingin panel lebih rapi, kamu bisa pakai subdomain khusus panel.

Contoh:
- `cp.mitrasejatiberkah.com`

Saran:
- pakai domain panel khusus
- tetap `DNS only`
- jangan dijadikan domain publik aplikasi
- port `8443` tetap dibatasi di firewall

## 7. Software tambahan di server

Masuk SSH lalu install:

```bash
apt update
apt install -y supervisor
```

Cek apakah `composer`, `node`, dan `npm` sudah tersedia:

```bash
composer --version || true
node -v || true
npm -v || true
```

Kalau `composer` belum ada:

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

Kalau `node` / `npm` belum ada, install:

```bash
apt install -y nodejs npm
node -v
npm -v
```

Catatan:
- kalau versi `node` terlalu lama untuk build Vite, install Node versi yang lebih baru sebelum `npm run build`
- untuk tim kecil, build di server masih layak
- untuk deploy yang lebih rapi, build asset bisa dilakukan dari lokal/CI lalu hasilnya dibawa ke server

## 8. Struktur folder yang disarankan

Contoh yang dipakai di dokumen ini:

- env `tes`
  - domain: `teserpos.mitrasejatiberkah.com`
  - site user: `teserpos`
  - path code: `/home/teserpos/htdocs/teserpos.mitrasejatiberkah.com`
  - document root: `/home/teserpos/htdocs/teserpos.mitrasejatiberkah.com/public`

- env `prod`
  - domain: `erpos.mitrasejatiberkah.com`
  - site user: `erpos`
  - path code: `/home/erpos/htdocs/erpos.mitrasejatiberkah.com`
  - document root: `/home/erpos/htdocs/erpos.mitrasejatiberkah.com/public`

Catatan penting:
- di CloudPanel, usahakan semua file project dimiliki oleh `site user`
- hindari menjalankan `composer`, `artisan`, dan `npm` sebagai `root` di folder site
- ini penting supaya tidak kena error permission di `storage` dan `bootstrap/cache`

## 8.1. Setup DNS di Cloudflare

Bagian ini penting karena domain aplikasi kamu diarahkan lewat `Cloudflare`.

Contoh target:
- `teserpos.mitrasejatiberkah.com`
- `erpos.mitrasejatiberkah.com`
- opsional panel:
  - `cp.mitrasejatiberkah.com`

### 8.1A. Tambah DNS record

Di `Cloudflare`:
1. buka domain
2. buka menu `DNS`
3. klik `Add record`

Tambahkan:

#### Untuk env tes
- `Type`: `A`
- `Name`: `teserpos`
- `IPv4 address`: `YOUR_STATIC_IP`
- `Proxy status`: `DNS only`
- `TTL`: `Auto`

#### Untuk env prod
- `Type`: `A`
- `Name`: `erpos`
- `IPv4 address`: `YOUR_STATIC_IP`
- `Proxy status`: `DNS only`
- `TTL`: `Auto`

#### Untuk panel CloudPanel opsional
- `Type`: `A`
- `Name`: `cp`
- `IPv4 address`: `YOUR_STATIC_IP`
- `Proxy status`: `DNS only`
- `TTL`: `Auto`

### 8.1B. Kenapa mulai dari `DNS only`

Saat setup awal:
- `DNS only` lebih mudah untuk debugging
- SSL origin lebih mudah dicek
- kalau ada masalah Nginx/site root, kamu tidak kebingungan antara masalah server dan masalah proxy Cloudflare

### 8.1C. Setelah SSL origin normal

Kalau:
- site sudah bisa dibuka normal via HTTPS
- sertifikat origin sudah benar

maka untuk domain aplikasi publik:
- `teserpos`
- `erpos`

kamu boleh ubah jadi:
- `Proxied`

Saran:
- untuk aplikasi publik: boleh `Proxied`
- untuk panel `cp`: tetap `DNS only`

### 8.1D. Mode SSL Cloudflare

Kalau domain aplikasi sudah dipasang Let's Encrypt di origin:
- set `Cloudflare SSL/TLS mode` ke:
  - `Full (strict)`

Jangan pakai:
- `Flexible`

Karena:
- `Flexible` sering bikin redirect loop / header campur
- `Full (strict)` paling aman kalau origin sudah punya sertifikat valid

### 8.1E. Cek propagasi DNS

Cek dari laptop atau server:

```bash
nslookup teserpos.mitrasejatiberkah.com
nslookup erpos.mitrasejatiberkah.com
```

Kalau IP sudah mengarah ke static IP Lightsail, lanjut ke step berikut.

## 9. Metode deploy yang direkomendasikan di CloudPanel

Untuk repo ini, saya sarankan:

1. buat site di CloudPanel
2. buat database di CloudPanel
3. clone repo via SSH sebagai site user
4. isi `.env`
5. inisialisasi DB
6. build asset
7. storage link + cache
8. cron + queue
9. smoke test

Rekomendasi utama:
- **deploy manual dengan `git clone`**

Kenapa:
- lebih mudah kontrol dua env:
  - `teserpos`
  - `erpos`
- lebih mudah lihat error `composer`, `artisan`, `npm build`, dan permission
- lebih mudah sinkron dengan SOP deploy yang sudah ada

Untuk repo ini, branch deploy yang benar:
- `master`

## 10. Buat site di CloudPanel

Di CloudPanel:
1. buka `Sites`
2. klik `Add Site`
3. pilih `Create a PHP Site`

Untuk env `tes`:
- `Domain Name`:
  - `teserpos.mitrasejatiberkah.com`
- `PHP Version`:
  - `PHP 8.3`
- `Site User`:
  - `teserpos`
- `Site User Password`:
  - password kuat
- `Vhost Root Directory` / `Site Root`:
  - arahkan nanti ke folder `public`

Untuk env `prod`:
- `Domain Name`:
  - `erpos.mitrasejatiberkah.com`
- `PHP Version`:
  - `PHP 8.3`
- `Site User`:
  - `erpos`
- `Site User Password`:
  - password kuat

Catat:
- site user
- password site user
- home directory site

## 11. Pasang SSL di CloudPanel

Lakukan ini setelah DNS mengarah benar.

Di tiap site:
1. buka site
2. buka `SSL/TLS`
3. pilih `Let's Encrypt`
4. issue certificate untuk domain site

Lakukan untuk:
- `teserpos.mitrasejatiberkah.com`
- `erpos.mitrasejatiberkah.com`

Kalau kamu juga pakai:
- `cp.mitrasejatiberkah.com`

bisa issue Let's Encrypt juga untuk panel domain itu.

Saran urutan:
1. DNS `DNS only`
2. issue Let's Encrypt
3. cek HTTPS normal
4. baru pertimbangkan `Proxied` untuk domain aplikasi

## 12. Buat database di CloudPanel

Di `Databases`:
1. buat database untuk `tes`
2. buat database untuk `prod`

Contoh:

### Env tes
- DB name:
  - `pgpos_tes`
- DB user:
  - `pgpos_tes_user`
- DB password:
  - password kuat

### Env prod
- DB name:
  - `pgpos_prod`
- DB user:
  - `pgpos_prod_user`
- DB password:
  - password kuat

Catat baik-baik:
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## 13. Clone code dari GitHub

Masuk ke server, lalu jalankan command sebagai `site user`.

### Env tes

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs && git clone https://github.com/mochagsr/testcode1.git teserpos.mitrasejatiberkah.com"
```

### Env prod

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs && git clone https://github.com/mochagsr/testcode1.git erpos.mitrasejatiberkah.com"
```

Kalau repo private:
- pakai SSH key deploy user
- atau personal access token

Kalau repo public:
- HTTPS seperti contoh di atas sudah cukup

## 14. Siapkan `.env`

### Env tes

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && cp .env.cloudpanel.test.example .env"
```

### Env prod

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && cp .env.cloudpanel.prod.example .env"
```

Lalu edit `.env`.

Contoh `tes`:

```env
APP_NAME=PgPOS-ERP-TES
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://teserpos.mitrasejatiberkah.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pgpos_tes
DB_USERNAME=pgpos_tes_user
DB_PASSWORD=replace_me
```

Contoh `prod`:

```env
APP_NAME=PgPOS-ERP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erpos.mitrasejatiberkah.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pgpos_prod
DB_USERNAME=pgpos_prod_user
DB_PASSWORD=replace_me
```

Lalu jalankan:

### Env tes

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan key:generate"
```

### Env prod

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan key:generate"
```

## 15. Arahkan document root ke `public`

Ini wajib untuk Laravel.

Pastikan document root tiap site mengarah ke:

### Env tes
- `/home/teserpos/htdocs/teserpos.mitrasejatiberkah.com/public`

### Env prod
- `/home/erpos/htdocs/erpos.mitrasejatiberkah.com/public`

Kalau document root masih ke root project:
- route seperti `/login` bisa gagal
- asset bisa kacau
- file sensitif di root project berisiko terbaca

## 16. Install dependency PHP dan frontend

### Env tes

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && composer install --no-dev --optimize-autoloader"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && npm install"
```

### Env prod

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && composer install --no-dev --optimize-autoloader"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && npm install"
```

## 17. Inisialisasi database

### Env tes

Pilihan yang disarankan:

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && mysql -u pgpos_tes_user -p pgpos_tes < database/sql/tespgpos_mysql_test_snapshot.sql"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan migrate --force"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan db:seed --force"
```

### Env prod

Pilihan aman:

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan migrate --force"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan db:seed --force"
```

Atau kalau mau import bootstrap:

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && mysql -u pgpos_prod_user -p pgpos_prod < database/sql/tespgpos_mysql_prod_bootstrap.sql"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan db:seed --force"
```

## 18. Build asset frontend

### Env tes

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && npm run build"
```

### Env prod

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && npm run build"
```

Urutan normal:
- install dependency
- inisialisasi DB
- build asset
- storage link + cache
- cron + queue
- smoke test

## 19. Link storage dan cache production

### Env tes

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan storage:link"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan optimize:clear"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan config:cache"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan view:cache"
```

### Env prod

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan storage:link"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan optimize:clear"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan config:cache"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan view:cache"
```

Catatan:
- untuk server yang pernah kena issue permission, selalu jalankan command Laravel sebagai `site user`
- jangan biasakan menjalankan `artisan` sebagai `root`

## 20. Cron scheduler dan queue

Di CloudPanel:
1. buka `Cron Jobs`
2. buat job baru
3. pilih user yang sesuai dengan site

### Scheduler `tes`

User:
- `teserpos`

Command:

```bash
cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan schedule:run >> /dev/null 2>&1
```

Interval:
- every minute

### Queue `tes`

User:
- `teserpos`

Command:

```bash
cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

Interval:
- every minute

### Scheduler `prod`

User:
- `erpos`

Command:

```bash
cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan schedule:run >> /dev/null 2>&1
```

Interval:
- every minute

### Queue `prod`

User:
- `erpos`

Command:

```bash
cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

Interval:
- every minute

## 21. Backup awal dan smoke test

Catatan penting:
- di server production, jalur yang disarankan adalah:
  - `php artisan app:deploy-check --skip-ops`
- ini cocok setelah `composer install --no-dev`
- kalau `artisan test` tidak tersedia di server, command ini akan otomatis fallback ke:
  - `php artisan app:http-smoke-test`
- `php artisan test ...` lebih cocok dijalankan di lokal / development yang masih punya `dev dependencies`

### Env tes

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan app:db-backup"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan app:integrity-check"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan app:load-test-light --loops=80 --search=ang"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan app:smoke-test"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan app:deploy-check --skip-ops"
```

### Env prod

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan app:db-backup"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan app:integrity-check"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan app:smoke-test"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan app:deploy-check --skip-ops"
```

## 22. Contoh alur lengkap env `tes`

```bash
ssh ubuntu@YOUR_STATIC_IP
sudo -i
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs && git clone https://github.com/mochagsr/testcode1.git teserpos.mitrasejatiberkah.com"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && cp .env.cloudpanel.test.example .env"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan key:generate"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && composer install --no-dev --optimize-autoloader"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && npm install"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && mysql -u pgpos_tes_user -p pgpos_tes < database/sql/tespgpos_mysql_test_snapshot.sql"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan migrate --force"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan db:seed --force"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && npm run build"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan storage:link"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan optimize:clear"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan config:cache"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan view:cache"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan app:deploy-check --skip-ops"
```

## 23. Contoh alur lengkap env `prod`

```bash
ssh ubuntu@YOUR_STATIC_IP
sudo -i
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs && git clone https://github.com/mochagsr/testcode1.git erpos.mitrasejatiberkah.com"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && cp .env.cloudpanel.prod.example .env"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan key:generate"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && composer install --no-dev --optimize-autoloader"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && npm install"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan migrate --force"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan db:seed --force"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && npm run build"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan storage:link"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan optimize:clear"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan config:cache"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan view:cache"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan app:deploy-check --skip-ops"
```

## 24. Langkah uji setelah deploy

Tes minimal:
1. buka `/login`
2. login admin
3. login user
4. buka:
   - `Barang`
   - `Customer`
   - `Supplier`
   - `Faktur Penjualan`
   - `Surat Pesanan`
   - `Surat Jalan`
   - `Tanda Terima Barang`
   - `Piutang`
   - `Hutang Supplier`
5. jalankan:
   - print
   - PDF
   - Excel
6. jalankan:
   - `php artisan app:deploy-check --skip-ops`

## 25. Update program / upgrade aplikasi

### 25A. Update kecil

Pakai ini kalau perubahan hanya:
- Blade/view
- CSS kecil
- teks
- bugfix ringan tanpa migration

### Env tes

```bash
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && git pull origin master"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan optimize:clear"
sudo -u teserpos -H bash -lc "cd /home/teserpos/htdocs/teserpos.mitrasejatiberkah.com && php artisan view:cache"
```

### Env prod

```bash
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && git pull origin master"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan optimize:clear"
sudo -u erpos -H bash -lc "cd /home/erpos/htdocs/erpos.mitrasejatiberkah.com && php artisan view:cache"
```

### 25B. Update dengan migration

Pakai ini kalau ada:
- file migration baru
- perubahan kolom/tabel

```bash
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && git pull origin master"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan migrate --force"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan optimize:clear"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan config:cache"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan view:cache"
```

### 25C. Update aman untuk semua kondisi

Kalau bingung perubahan apa saja yang masuk, pakai jalur aman:

```bash
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && git pull origin master"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && composer install --no-dev --optimize-autoloader"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan migrate --force"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && npm install"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && npm run build"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan optimize:clear"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan config:cache"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan view:cache"
sudo -u SITEUSER -H bash -lc "cd /home/SITEUSER/htdocs/DOMAIN && php artisan app:deploy-check --skip-ops"
```

## 26. Dokumen pendamping

- `README.md`
- `docs/UAT_AAPANEL_POST_DEPLOY.md`
- `docs/BACKUP_OPS_HEALTH_README.md`
- `docs/GO_LIVE_RUNBOOK.md`
- `docs/RECOVERY_SOP.md`

Catatan:
- nama file `UAT_AAPANEL_POST_DEPLOY.md` masih memakai nama lama
- tapi checklist isinya tetap bisa dipakai juga untuk env non-aaPanel

## 27. Saran praktik

1. pakai `Static IP` di Lightsail
2. jalankan seluruh command site sebagai `site user`, bukan `root`
3. mulai dari `Cloudflare DNS only`
4. issue Let's Encrypt di origin
5. setelah itu baru pindah aplikasi publik ke `Cloudflare Proxied`
6. set `Cloudflare SSL/TLS mode` ke `Full (strict)`
7. panel domain `cp` tetap `DNS only`
8. batasi port `22` dan `8443` ke IP admin kalau memungkinkan
9. buat snapshot / backup instance Lightsail sebelum upgrade besar
10. lakukan update di `teserpos` dulu sebelum `erpos`

